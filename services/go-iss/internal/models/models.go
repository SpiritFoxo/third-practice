package models

import (
	"time"

	"gorm.io/datatypes"
)

type IssFetchLog struct {
	ID        uint           `gorm:"primaryKey"`
	FetchedAt time.Time      `gorm:"not null;default:now()"`
	SourceURL string         `gorm:"not null"`
	Payload   datatypes.JSON `gorm:"not null"`
}

func (IssFetchLog) TableName() string {
	return "iss_fetch_log"
}

type OsdrItem struct {
	ID         uint    `gorm:"primaryKey"`
	DatasetID  *string `gorm:"uniqueIndex"`
	Title      *string
	Status     *string
	UpdatedAt  *time.Time
	InsertedAt time.Time      `gorm:"not null;default:now()"`
	Raw        datatypes.JSON `gorm:"not null"`
}

func (OsdrItem) TableName() string {
	return "osdr_items"
}

type SpaceCache struct {
	ID        uint           `gorm:"primaryKey"`
	Source    string         `gorm:"not null;index"`
	FetchedAt time.Time      `gorm:"not null;default:now()"`
	Payload   datatypes.JSON `gorm:"not null"`
}

func (SpaceCache) TableName() string {
	return "space_cache"
}
