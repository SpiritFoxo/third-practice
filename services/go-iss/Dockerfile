FROM golang:1.25.4-trixie AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build -o iss_service ./cmd/main.go

FROM alpine:3.22.2

WORKDIR /root/
COPY --from=builder /app/iss_service .

EXPOSE 8082

CMD ["./iss_service"]