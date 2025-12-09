package main

import (
	"context"
	"encoding/csv"
	"errors"
	"fmt"
	"log"
	"math/rand"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"time"

	"github.com/xuri/excelize/v2"
)

var (
	infoLogger  *log.Logger
	errorLogger *log.Logger
)

type TelemetryData struct {
	RecordedAt time.Time
	Voltage    float64
	Temp       float64
	IsValid    bool
	SourceFile string
}

func initLoggers() {
	infoLogger = log.New(os.Stdout, "[GenService] INFO: ", log.LstdFlags)
	errorLogger = log.New(os.Stderr, "[GenService] ERROR: ", log.LstdFlags)
}

func getEnvDef(name, def string) string {
	if v, ok := os.LookupEnv(name); ok && v != "" {
		return v
	}
	return def
}

func randFloat(minV, maxV float64) float64 {
	return minV + rand.Float64()*(maxV-minV)
}

func generateData(filename string) TelemetryData {
	return TelemetryData{
		RecordedAt: time.Now(),
		Voltage:    randFloat(3.2, 12.6),
		Temp:       randFloat(-50.0, 80.0),
		IsValid:    rand.Intn(2) == 1,
		SourceFile: filename,
	}
}

func saveCSV(outDir, filename string, data TelemetryData) (string, error) {
	fullpath := filepath.Join(outDir, filename)
	f, err := os.Create(fullpath)
	if err != nil {
		return "", fmt.Errorf("create csv file: %w", err)
	}
	defer f.Close()

	writer := csv.NewWriter(f)
	defer writer.Flush()

	headers := []string{"recorded_at", "voltage", "temp", "is_valid", "source_file"}
	if err := writer.Write(headers); err != nil {
		return "", err
	}

	tStr := data.RecordedAt.Format("2006-01-02 15:04:05")
	vStr := fmt.Sprintf("%.2f", data.Voltage)
	tempStr := fmt.Sprintf("%.2f", data.Temp)
	boolStr := "ЛОЖЬ"
	if data.IsValid {
		boolStr = "ИСТИНА"
	}

	record := []string{tStr, vStr, tempStr, boolStr, data.SourceFile}
	if err := writer.Write(record); err != nil {
		return "", err
	}

	return fullpath, nil
}

func saveXLSX(outDir, filenameBase string, data TelemetryData) (string, error) {
	xlsxName := filenameBase + ".xlsx"
	fullpath := filepath.Join(outDir, xlsxName)

	f := excelize.NewFile()
	defer func() {
		if err := f.Close(); err != nil {
			errorLogger.Println(err)
		}
	}()

	sheet := "Sheet1"
	headers := []string{"Time (Timestamp)", "Voltage (Num)", "Temp (Num)", "Valid (Bool)", "Source (Text)"}
	for i, h := range headers {
		cell, _ := excelize.CoordinatesToCellName(i+1, 1)
		f.SetCellValue(sheet, cell, h)
	}

	f.SetCellValue(sheet, "A2", data.RecordedAt)
	styleID, _ := f.NewStyle(&excelize.Style{
		NumFmt: 22,
	})
	f.SetCellStyle(sheet, "A2", "A2", styleID)

	f.SetCellValue(sheet, "B2", data.Voltage)

	f.SetCellValue(sheet, "C2", data.Temp)

	f.SetCellValue(sheet, "D2", data.IsValid)

	f.SetCellValue(sheet, "E2", data.SourceFile)

	f.SetColWidth(sheet, "A", "A", 20)
	f.SetColWidth(sheet, "E", "E", 30)

	if err := f.SaveAs(fullpath); err != nil {
		return "", fmt.Errorf("save xlsx: %w", err)
	}

	return fullpath, nil
}

func generateFiles(outDir string) (string, error) {
	if outDir == "" {
		return "", errors.New("outDir is empty")
	}
	if err := os.MkdirAll(outDir, 0o755); err != nil {
		return "", fmt.Errorf("failed to create outDir: %w", err)
	}

	ts := time.Now().Format("20060102_150405")
	baseName := "telemetry_" + ts
	csvName := baseName + ".csv"

	data := generateData(csvName)

	csvPath, err := saveCSV(outDir, csvName, data)
	if err != nil {
		return "", err
	}
	infoLogger.Printf("CSV generated: %s", csvPath)

	xlsxPath, err := saveXLSX(outDir, baseName, data)
	if err != nil {
		errorLogger.Printf("Failed to generate XLSX: %v", err)
	} else {
		infoLogger.Printf("XLSX generated: %s", xlsxPath)
	}

	return csvPath, nil
}

func runPsqlCopy(ctx context.Context, fullpath string) error {
	pghost := getEnvDef("PGHOST", "db")
	pgport := getEnvDef("PGPORT", "5432")
	pguser := getEnvDef("PGUSER", "monouser")
	pgdb := getEnvDef("PGDATABASE", "monolith")

	connStr := fmt.Sprintf("host=%s port=%s user=%s dbname=%s", pghost, pgport, pguser, pgdb)

	copyCmd := fmt.Sprintf("\\copy telemetry_legacy(recorded_at, voltage, temp, is_valid, source_file) FROM '%s' WITH (FORMAT csv, HEADER true)", fullpath)

	cmd := exec.CommandContext(ctx, "psql", connStr, "-c", copyCmd)

	cmd.Env = os.Environ()
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr

	infoLogger.Printf("Running psql to import %s", fullpath)
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("psql copy failed: %w", err)
	}
	infoLogger.Printf("psql import finished for %s", fullpath)
	return nil
}

func runOnce() {
	outDir := getEnvDef("CSV_OUT_DIR", "/data/csv")
	csvPath, err := generateFiles(outDir)
	if err != nil {
		errorLogger.Printf("generateFiles failed: %v", err)
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := runPsqlCopy(ctx, csvPath); err != nil {
		errorLogger.Printf("runPsqlCopy failed: %v", err)
	}
}

func parsePeriod() int {
	v := getEnvDef("GEN_PERIOD_SEC", "")
	if v == "" {
		return 300
	}
	n, err := strconv.Atoi(v)
	if err != nil || n <= 0 {
		return 300
	}
	return n
}

func main() {
	initLoggers()

	if getEnvDef("RUN_ONCE", "") == "1" {
		infoLogger.Println("RUN_ONCE=1 detected — executing single run and exiting.")
		runOnce()
		return
	}

	period := parsePeriod()
	infoLogger.Printf("Service started. Generating XLSX/CSV data every %d seconds.", period)

	ticker := time.NewTicker(time.Duration(period) * time.Second)
	defer ticker.Stop()
	runOnce()

	for range ticker.C {
		runOnce()
	}
}
