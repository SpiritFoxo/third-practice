package main

import (
	"context"
	"errors"
	"fmt"
	"log"
	"math/rand"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"time"
)

var (
	infoLogger  *log.Logger
	errorLogger *log.Logger
)

func initLoggers() {
	infoLogger = log.New(os.Stdout, "[LegacyCSV] INFO: ", log.LstdFlags)
	errorLogger = log.New(os.Stderr, "[LegacyCSV] ERROR: ", log.LstdFlags)
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

func generateCSV(outDir string) (string, error) {
	if outDir == "" {
		return "", errors.New("outDir is empty")
	}
	if err := os.MkdirAll(outDir, 0o755); err != nil {
		return "", fmt.Errorf("failed to create outDir: %w", err)
	}

	ts := time.Now().Format("20060102_150405")
	fn := "telemetry_" + ts + ".csv"
	fullpath := filepath.Join(outDir, fn)

	f, err := os.Create(fullpath)
	if err != nil {
		return "", fmt.Errorf("create file: %w", err)
	}
	defer f.Close()

	_, _ = fmt.Fprintln(f, "recorded_at,voltage,temp,source_file")
	recordedAt := time.Now().Format("2006-01-02 15:04:05")
	voltage := fmt.Sprintf("%.2f", randFloat(3.2, 12.6))
	temp := fmt.Sprintf("%.2f", randFloat(-50.0, 80.0))
	line := fmt.Sprintf("%s,%s,%s,%s", recordedAt, voltage, temp, fn)
	_, _ = fmt.Fprintln(f, line)

	infoLogger.Printf("CSV generated: %s (record: %s)", fullpath, line)
	return fullpath, nil
}

func runPsqlCopy(ctx context.Context, fullpath string) error {
	pghost := getEnvDef("PGHOST", "db")
	pgport := getEnvDef("PGPORT", "5432")
	pguser := getEnvDef("PGUSER", "monouser")
	pgdb := getEnvDef("PGDATABASE", "monolith")

	connStr := fmt.Sprintf("host=%s port=%s user=%s dbname=%s", pghost, pgport, pguser, pgdb)

	copyCmd := fmt.Sprintf("\\copy telemetry_legacy(recorded_at, voltage, temp, source_file) FROM '%s' WITH (FORMAT csv, HEADER true)", fullpath)

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

	fullpath, err := generateCSV(outDir)
	if err != nil {
		errorLogger.Printf("generateCSV failed: %v", err)
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := runPsqlCopy(ctx, fullpath); err != nil {
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
	rand.Seed(time.Now().UnixNano())
	initLoggers()

	if getEnvDef("RUN_ONCE", "") == "1" {
		infoLogger.Println("RUN_ONCE=1 detected — executing single run and exiting.")
		runOnce()
		return
	}

	period := parsePeriod()
	infoLogger.Printf("Legacy service started. Generating data every %d seconds.", period)

	ticker := time.NewTicker(time.Duration(period) * time.Second)
	defer ticker.Stop()
	runOnce()

	for {
		select {
		case <-ticker.C:
			runOnce()
		}
	}
}
