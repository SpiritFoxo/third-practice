package routers

import (
	"go_iss/internal/handlers"

	"github.com/gin-gonic/gin"
)

func SetupRouter(h *handlers.Handler) *gin.Engine {
	r := gin.Default()

	r.GET("/health", h.Health)

	r.GET("/last", h.LastIss)
	r.GET("/fetch", h.TriggerIss)
	r.GET("/iss/trend", h.IssTrend)

	r.GET("/osdr/sync", h.OsdrSync)
	r.GET("/osdr/list", h.OsdrList)

	space := r.Group("/space")
	{
		space.GET("/:src/latest", h.SpaceLatest)
		space.GET("/refresh", h.SpaceRefresh)
		space.GET("/summary", h.SpaceSummary)
	}

	return r
}
