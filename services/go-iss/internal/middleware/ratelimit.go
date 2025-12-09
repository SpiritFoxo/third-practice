package middleware

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/ulule/limiter/v3"
	"github.com/ulule/limiter/v3/drivers/store/memory"
)

func RateLimiterManual() gin.HandlerFunc {
	rate := limiter.Rate{
		Period: time.Minute,
		Limit:  60,
	}
	store := memory.NewStore()
	lim := limiter.New(store, rate, limiter.WithTrustForwardHeader(true))

	return func(c *gin.Context) {
		key := c.ClientIP()

		result, err := lim.Get(c.Request.Context(), key)
		if err != nil {
			c.AbortWithStatusJSON(http.StatusInternalServerError, gin.H{
				"error": "internal server error",
			})
			return
		}

		if result.Reached {
			c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{
				"success": false,
				"error": gin.H{
					"code":    "rate_limit_exceeded",
					"message": "Too many requests",
				},
			})
			return
		}

		c.Next()
	}
}
