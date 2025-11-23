package services

import (
	"encoding/json"
	"go_iss/internal/models"
	"go_iss/internal/repositories"
	"math"
	"strconv"
	"time"
)

type IssService struct {
	repo *repositories.IssRepository
}

func NewIssService(repo *repositories.IssRepository) *IssService {
	return &IssService{repo: repo}
}

type Trend struct {
	Movement    bool       `json:"movement"`
	DeltaKm     float64    `json:"delta_km"`
	DtSec       float64    `json:"dt_sec"`
	VelocityKmh *float64   `json:"velocity_kmh"`
	FromTime    *time.Time `json:"from_time"`
	ToTime      *time.Time `json:"to_time"`
	FromLat     *float64   `json:"from_lat"`
	FromLon     *float64   `json:"from_lon"`
	ToLat       *float64   `json:"to_lat"`
	ToLon       *float64   `json:"to_lon"`
}

func (s *IssService) GetLast() (*models.IssFetchLog, error) {
	return s.repo.GetLast()
}

func (s *IssService) GetTrend() (*Trend, error) {
	logs, err := s.repo.GetLastTwo()
	if err != nil {
		return nil, err
	}

	if len(logs) < 2 {
		return &Trend{}, nil
	}

	curr, prev := logs[0], logs[1]

	var currP, prevP map[string]interface{}
	json.Unmarshal(curr.Payload, &currP)
	json.Unmarshal(prev.Payload, &prevP)

	lat1 := getFloat(prevP, "latitude")
	lon1 := getFloat(prevP, "longitude")
	lat2 := getFloat(currP, "latitude")
	lon2 := getFloat(currP, "longitude")
	vel2 := getFloat(currP, "velocity")

	deltaKm := 0.0
	movement := false
	if lat1 != nil && lon1 != nil && lat2 != nil && lon2 != nil {
		deltaKm = haversineKm(*lat1, *lon1, *lat2, *lon2)
		movement = deltaKm > 0.1
	}

	dtSec := curr.FetchedAt.Sub(prev.FetchedAt).Seconds()

	return &Trend{
		Movement:    movement,
		DeltaKm:     deltaKm,
		DtSec:       dtSec,
		VelocityKmh: vel2,
		FromTime:    &prev.FetchedAt,
		ToTime:      &curr.FetchedAt,
		FromLat:     lat1,
		FromLon:     lon1,
		ToLat:       lat2,
		ToLon:       lon2,
	}, nil
}

func getFloat(m map[string]interface{}, key string) *float64 {
	if v, ok := m[key]; ok {
		if f, ok := v.(float64); ok {
			return &f
		}
		if s, ok := v.(string); ok {
			if f, err := strconv.ParseFloat(s, 64); err == nil {
				return &f
			}
		}
	}
	return nil
}

func haversineKm(lat1, lon1, lat2, lon2 float64) float64 {
	toRad := math.Pi / 180
	rlat1 := lat1 * toRad
	rlat2 := lat2 * toRad
	dlat := (lat2 - lat1) * toRad
	dlon := (lon2 - lon1) * toRad

	a := math.Sin(dlat/2)*math.Sin(dlat/2) +
		math.Cos(rlat1)*math.Cos(rlat2)*math.Sin(dlon/2)*math.Sin(dlon/2)
	c := 2 * math.Atan2(math.Sqrt(a), math.Sqrt(1-a))
	return 6371.0 * c
}
