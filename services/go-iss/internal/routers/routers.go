package routers

import (
	"go_iss/internal/handlers"
	"go_iss/internal/middleware"

	"github.com/gin-gonic/gin"
)

func SetupRouter(h *handlers.Handler) *gin.Engine {
	r := gin.Default()

	r.GET("/health", middleware.RateLimiterManual(), h.Health)

	r.GET("/last", middleware.RateLimiterManual(), h.LastIss)
	r.GET("/fetch", middleware.RateLimiterManual(), h.TriggerIss)
	r.GET("/iss/trend", middleware.RateLimiterManual(), h.IssTrend)

	r.GET("/osdr/sync", middleware.RateLimiterManual(), h.OsdrSync)
	r.GET("/osdr/list", middleware.RateLimiterManual(), h.OsdrList)

	space := r.Group("/space")
	{
		space.GET("/:src/latest", middleware.RateLimiterManual(), h.SpaceLatest)
		space.GET("/refresh", middleware.RateLimiterManual(), h.SpaceRefresh)
		space.GET("/summary", middleware.RateLimiterManual(), h.SpaceSummary)
	}

	return r
}
