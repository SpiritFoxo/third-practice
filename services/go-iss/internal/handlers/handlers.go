package handlers

import (
	"go_iss/internal/models"
	"go_iss/internal/repositories"
	"go_iss/internal/services"
	"net/http"
	"strings"
	"time"

	"github.com/gin-gonic/gin"
)

type Handler struct {
	issService     *services.IssService
	fetcherService *services.FetcherService
	issRepo        *repositories.IssRepository
	osdrRepo       *repositories.OsdrRepository
	cacheRepo      *repositories.SpaceCacheRepository
}

func NewHandler(
	issS *services.IssService,
	fetcherS *services.FetcherService,
	issR *repositories.IssRepository,
	osdrR *repositories.OsdrRepository,
	cacheR *repositories.SpaceCacheRepository,
) *Handler {
	return &Handler{issService: issS, fetcherService: fetcherS, issRepo: issR, osdrRepo: osdrR, cacheRepo: cacheR}
}

func (h *Handler) Health(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{"status": "ok", "now": time.Now()})
}

func (h *Handler) LastIss(c *gin.Context) {
	log, err := h.issRepo.GetLast()
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	if log == nil {
		c.JSON(http.StatusOK, gin.H{"message": "no data"})
		return
	}
	c.JSON(http.StatusOK, log)
}

func (h *Handler) TriggerIss(c *gin.Context) {
	if err := h.fetcherService.FetchAndStoreISS(); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	h.LastIss(c)
}

func (h *Handler) IssTrend(c *gin.Context) {
	trend, err := h.issService.GetTrend()
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, trend)
}

func (h *Handler) OsdrSync(c *gin.Context) {
	go h.fetcherService.FetchAndStoreOSDR()
	c.JSON(http.StatusOK, gin.H{"message": "sync triggered"})
}

func (h *Handler) OsdrList(c *gin.Context) {
	list, err := h.osdrRepo.List(20)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"items": list})
}

func (h *Handler) SpaceLatest(c *gin.Context) {
	src := c.Param("src")
	res, err := h.cacheRepo.GetLatest(src)
	if err != nil {

		c.JSON(http.StatusOK, gin.H{"source": src, "message": "no data"})
		return
	}
	c.JSON(http.StatusOK, res)
}

func (h *Handler) SpaceRefresh(c *gin.Context) {
	srcs := c.DefaultQuery("src", "apod,neo,flr,cme,spacex")
	list := strings.Split(srcs, ",")

	done := []string{}
	for _, s := range list {
		s = strings.TrimSpace(s)
		var err error
		switch s {
		case "apod":
			err = h.fetcherService.FetchApod()
		case "neo":
			err = h.fetcherService.FetchNeo()
		case "flr":
			err = h.fetcherService.FetchDonki()
		case "spacex":
			err = h.fetcherService.FetchSpaceX()
		}
		if err == nil {
			done = append(done, s)
		}
	}
	c.JSON(http.StatusOK, gin.H{"refreshed": done})
}

func (h *Handler) SpaceSummary(c *gin.Context) {
	apod, _ := h.cacheRepo.GetLatest("apod")
	neo, _ := h.cacheRepo.GetLatest("neo")
	flr, _ := h.cacheRepo.GetLatest("flr")
	cme, _ := h.cacheRepo.GetLatest("cme")
	spacex, _ := h.cacheRepo.GetLatest("spacex")
	iss, _ := h.issRepo.GetLast()
	osdrCount, _ := h.osdrRepo.Count()

	c.JSON(http.StatusOK, gin.H{
		"apod":       orEmpty(apod),
		"neo":        orEmpty(neo),
		"flr":        orEmpty(flr),
		"cme":        orEmpty(cme),
		"spacex":     orEmpty(spacex),
		"iss":        iss,
		"osdr_count": osdrCount,
	})
}

func orEmpty(v interface{}) interface{} {
	if v == nil || (v == (*models.SpaceCache)(nil)) {
		return gin.H{}
	}
	return v
}
