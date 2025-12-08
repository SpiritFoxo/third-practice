FROM golang:1.25.4-trixie AS builder

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        git ca-certificates && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /src
COPY go.mod ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o /app/legacycsv ./main.go

FROM debian:bookworm-slim

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      postgresql-client cron ca-certificates && \
    rm -rf /var/lib/apt/lists/*

RUN mkdir -p /app /data/csv
WORKDIR /app

COPY --from=builder /app/legacycsv /app/legacycsv
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV CSV_OUT_DIR=/data/csv \
    GEN_PERIOD_SEC=300 \
    CRON_SCHEDULE="*/5 * * * *" \
    PGHOST=db \
    PGPORT=5432 \
    PGUSER=monouser \
    PGDATABASE=monolith

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD []
