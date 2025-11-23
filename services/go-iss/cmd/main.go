package main

import (
	"log"

	"go_iss/internal/config"
	"go_iss/internal/handlers"
	"go_iss/internal/models"
	"go_iss/internal/repositories"
	"go_iss/internal/routers"
	"go_iss/internal/services"

	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

func main() {
	cfg := config.Load()

	db, err := gorm.Open(postgres.Open(cfg.DatabaseURL), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}

	err = db.AutoMigrate(&models.IssFetchLog{}, &models.OsdrItem{}, &models.SpaceCache{})
	if err != nil {
		log.Fatalf("Migration failed: %v", err)
	}

	issRepo := repositories.NewIssRepository(db)
	osdrRepo := repositories.NewOsdrRepository(db)
	cacheRepo := repositories.NewSpaceCacheRepository(db)

	issService := services.NewIssService(issRepo)
	fetcherService := services.NewFetcherService(cfg, issRepo, osdrRepo, cacheRepo)

	fetcherService.StartBackgroundJobs()

	h := handlers.NewHandler(issService, fetcherService, issRepo, osdrRepo, cacheRepo)
	r := routers.SetupRouter(h)

	if err := r.Run(":3000"); err != nil {
		log.Fatal(err)
	}
}
