package repositories

import (
	"go_iss/internal/models"

	"gorm.io/datatypes"
	"gorm.io/gorm"
	"gorm.io/gorm/clause"
)

type IssRepository struct {
	db *gorm.DB
}

func NewIssRepository(db *gorm.DB) *IssRepository {
	return &IssRepository{db: db}
}

func (r *IssRepository) Create(log *models.IssFetchLog) error {
	return r.db.Create(log).Error
}

func (r *IssRepository) GetLast() (*models.IssFetchLog, error) {
	var log models.IssFetchLog
	err := r.db.Order("id desc").First(&log).Error
	if err != nil {
		return nil, err
	}
	return &log, nil
}

func (r *IssRepository) GetLastTwo() ([]models.IssFetchLog, error) {
	var logs []models.IssFetchLog
	err := r.db.Order("id desc").Limit(2).Find(&logs).Error
	return logs, err
}

type OsdrRepository struct {
	db *gorm.DB
}

func NewOsdrRepository(db *gorm.DB) *OsdrRepository {
	return &OsdrRepository{db: db}
}

func (r *OsdrRepository) Upsert(item *models.OsdrItem) error {
	return r.db.Clauses(clause.OnConflict{
		Columns:   []clause.Column{{Name: "dataset_id"}},
		DoUpdates: clause.AssignmentColumns([]string{"title", "status", "updated_at", "raw"}),
	}).Create(item).Error
}

func (r *OsdrRepository) List(limit int) ([]models.OsdrItem, error) {
	var items []models.OsdrItem
	err := r.db.Order("inserted_at desc").Limit(limit).Find(&items).Error
	return items, err
}

func (r *OsdrRepository) Count() (int64, error) {
	var count int64
	err := r.db.Model(&models.OsdrItem{}).Count(&count).Error
	return count, err
}

type SpaceCacheRepository struct {
	db *gorm.DB
}

func NewSpaceCacheRepository(db *gorm.DB) *SpaceCacheRepository {
	return &SpaceCacheRepository{db: db}
}

func (r *SpaceCacheRepository) Save(source string, payload datatypes.JSON) error {
	cache := models.SpaceCache{Source: source, Payload: payload}
	return r.db.Create(&cache).Error
}

func (r *SpaceCacheRepository) GetLatest(source string) (*models.SpaceCache, error) {
	var cache models.SpaceCache
	err := r.db.Where("source = ?", source).Order("id desc").First(&cache).Error
	if err != nil {
		return nil, err
	}
	return &cache, nil
}
