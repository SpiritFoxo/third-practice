package config

import (
	"os"
	"strconv"

	"github.com/joho/godotenv"
)

type Config struct {
	DatabaseURL        string
	NasaAPIURL         string
	NasaAPIKey         string
	WhereIssURL        string
	FetchEverySeconds  int
	IssEverySeconds    int
	ApodEverySeconds   int
	NeoEverySeconds    int
	DonkiEverySeconds  int
	SpacexEverySeconds int
}

func Load() *Config {
	_ = godotenv.Load()

	return &Config{
		DatabaseURL:        getEnv("DATABASE_URL", ""),
		NasaAPIURL:         getEnv("NASA_API_URL", "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json"),
		NasaAPIKey:         getEnv("NASA_API_KEY", ""),
		WhereIssURL:        getEnv("WHERE_ISS_URL", "https://api.wheretheiss.at/v1/satellites/25544"),
		FetchEverySeconds:  getEnvInt("FETCH_EVERY_SECONDS", 600),
		IssEverySeconds:    getEnvInt("ISS_EVERY_SECONDS", 120),
		ApodEverySeconds:   getEnvInt("APOD_EVERY_SECONDS", 43200),
		NeoEverySeconds:    getEnvInt("NEO_EVERY_SECONDS", 7200),
		DonkiEverySeconds:  getEnvInt("DONKI_EVERY_SECONDS", 3600),
		SpacexEverySeconds: getEnvInt("SPACEX_EVERY_SECONDS", 3600),
	}
}

func getEnv(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func getEnvInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if i, err := strconv.Atoi(v); err == nil {
			return i
		}
	}
	return def
}
