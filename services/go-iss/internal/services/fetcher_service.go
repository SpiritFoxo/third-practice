package services

import (
	"encoding/json"
	"fmt"
	"go_iss/internal/config"
	"go_iss/internal/models"
	"go_iss/internal/repositories"
	"net/http"
	"time"

	"gorm.io/datatypes"
)

type FetcherService struct {
	cfg       *config.Config
	issRepo   *repositories.IssRepository
	osdrRepo  *repositories.OsdrRepository
	cacheRepo *repositories.SpaceCacheRepository
	client    *http.Client
}

func NewFetcherService(
	cfg *config.Config,
	iss *repositories.IssRepository,
	osdr *repositories.OsdrRepository,
	cache *repositories.SpaceCacheRepository,
) *FetcherService {
	return &FetcherService{
		cfg:       cfg,
		issRepo:   iss,
		osdrRepo:  osdr,
		cacheRepo: cache,
		client:    &http.Client{Timeout: 30 * time.Second},
	}
}

func (s *FetcherService) StartBackgroundJobs() {
	go s.loop(s.FetchAndStoreISS, s.cfg.IssEverySeconds)
	go s.loop(s.FetchAndStoreOSDR, s.cfg.FetchEverySeconds)
	go s.loop(s.FetchApod, s.cfg.ApodEverySeconds)
	go s.loop(s.FetchNeo, s.cfg.NeoEverySeconds)
	go s.loop(s.FetchDonki, s.cfg.DonkiEverySeconds)
	go s.loop(s.FetchSpaceX, s.cfg.SpacexEverySeconds)
}

func (s *FetcherService) loop(fn func() error, seconds int) {
	if seconds <= 0 {
		return
	}
	_ = fn()

	ticker := time.NewTicker(time.Duration(seconds) * time.Second)
	defer ticker.Stop()
	for range ticker.C {
		if err := fn(); err != nil {
			fmt.Printf("Job error: %v\n", err)
		}
	}
}

func (s *FetcherService) FetchAndStoreISS() error {
	data, err := s.fetchJSON(s.cfg.WhereIssURL)
	if err != nil {
		return err
	}

	b, _ := json.Marshal(data)

	return s.issRepo.Create(&models.IssFetchLog{
		SourceURL: s.cfg.WhereIssURL,
		Payload:   datatypes.JSON(b),
	})
}

func (s *FetcherService) FetchAndStoreOSDR() error {
	data, err := s.fetchJSON(s.cfg.NasaAPIURL)
	if err != nil {
		return err
	}

	var items []interface{}

	if arr, ok := data.([]interface{}); ok {
		items = arr
	} else if m, ok := data.(map[string]interface{}); ok {
		if i, ok := m["items"].([]interface{}); ok {
			items = i
		} else if r, ok := m["results"].([]interface{}); ok {
			items = r
		} else {
			items = []interface{}{m}
		}
	}

	count := 0
	for _, raw := range items {
		itemMap, ok := raw.(map[string]interface{})
		if !ok {
			continue
		}

		dsID := pickString(itemMap, "dataset_id", "id", "uuid", "studyId", "accession", "osdr_id")
		title := pickString(itemMap, "title", "name", "label")
		status := pickString(itemMap, "status", "state", "lifecycle")
		updated := pickTime(itemMap, "updated", "updated_at", "modified", "lastUpdated", "timestamp")

		rawBytes, _ := json.Marshal(itemMap)

		err := s.osdrRepo.Upsert(&models.OsdrItem{
			DatasetID: dsID,
			Title:     title,
			Status:    status,
			UpdatedAt: updated,
			Raw:       datatypes.JSON(rawBytes),
		})
		if err == nil {
			count++
		}
	}
	return nil
}

func (s *FetcherService) fetchAndCache(url, key, source string, params map[string]string) error {
	req, _ := http.NewRequest("GET", url, nil)
	q := req.URL.Query()
	if s.cfg.NasaAPIKey != "" && key != "" {
		q.Add(key, s.cfg.NasaAPIKey)
	}
	for k, v := range params {
		q.Add(k, v)
	}
	req.URL.RawQuery = q.Encode()

	resp, err := s.client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	var payload interface{}
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		return err
	}

	b, _ := json.Marshal(payload)
	return s.cacheRepo.Save(source, datatypes.JSON(b))
}

func (s *FetcherService) FetchApod() error {
	return s.fetchAndCache("https://api.nasa.gov/planetary/apod", "api_key", "apod", map[string]string{"thumbs": "true"})
}

func (s *FetcherService) FetchNeo() error {
	now := time.Now()
	start := now.AddDate(0, 0, -2).Format("2006-01-02")
	end := now.Format("2006-01-02")
	return s.fetchAndCache("https://api.nasa.gov/neo/rest/v1/feed", "api_key", "neo", map[string]string{
		"start_date": start,
		"end_date":   end,
	})
}

func (s *FetcherService) FetchDonki() error {
	now := time.Now()
	start := now.AddDate(0, 0, -5).Format("2006-01-02")
	end := now.Format("2006-01-02")

	err1 := s.fetchAndCache("https://api.nasa.gov/DONKI/FLR", "api_key", "flr", map[string]string{"startDate": start, "endDate": end})
	err2 := s.fetchAndCache("https://api.nasa.gov/DONKI/CME", "api_key", "cme", map[string]string{"startDate": start, "endDate": end})

	if err1 != nil {
		return err1
	}
	return err2
}

func (s *FetcherService) FetchSpaceX() error {
	return s.fetchAndCache("https://api.spacexdata.com/v4/launches/next", "", "spacex", nil)
}

func (s *FetcherService) fetchJSON(url string) (interface{}, error) {
	resp, err := s.client.Get(url)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	var data interface{}
	err = json.NewDecoder(resp.Body).Decode(&data)
	return data, err
}

func pickString(m map[string]interface{}, keys ...string) *string {
	for _, k := range keys {
		if v, ok := m[k]; ok {
			if s, ok := v.(string); ok && s != "" {
				return &s
			}
			if n, ok := v.(float64); ok {
				str := fmt.Sprintf("%.0f", n)
				return &str
			}
		}
	}
	return nil
}

func pickTime(m map[string]interface{}, keys ...string) *time.Time {
	for _, k := range keys {
		if v, ok := m[k]; ok {
			if s, ok := v.(string); ok {
				formats := []string{time.RFC3339, "2006-01-02 15:04:05", "2006-01-02"}
				for _, f := range formats {
					if t, err := time.Parse(f, s); err == nil {
						return &t
					}
				}
			}
		}
	}
	return nil
}
