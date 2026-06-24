# --- Stage 1: сборка ---
FROM golang:1.22-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN go build -o /notification-service ./cmd/server

# --- Stage 2: минимальный рантайм-образ ---
FROM alpine:3.19

RUN apk add --no-cache ca-certificates

COPY --from=builder /notification-service /usr/local/bin/notification-service

EXPOSE 8080

CMD ["notification-service"]
