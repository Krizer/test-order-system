package main

import (
	"database/sql"
	"fmt"
	"log"
	"net/http"
	"os"

	_ "github.com/lib/pq"

	"notification-service/internal/domain"
	"notification-service/internal/handler"
	"notification-service/internal/repository"
	"notification-service/internal/service"
	"notification-service/internal/strategy"
)

func getEnv(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

func main() {
	// --- Конфигурация из переменных окружения (как и в PHP-сервисе) ---
	dbHost := getEnv("DB_HOST", "postgres")
	dbPort := getEnv("DB_PORT", "5432")
	dbName := getEnv("DB_NAME", "orders_db")
	dbUser := getEnv("DB_USER", "orders_user")
	dbPassword := getEnv("DB_PASSWORD", "orders_password")
	port := getEnv("PORT", "8080")

	dsn := fmt.Sprintf(
		"host=%s port=%s dbname=%s user=%s password=%s sslmode=disable",
		dbHost, dbPort, dbName, dbUser, dbPassword,
	)

	db, err := sql.Open("postgres", dsn)
	if err != nil {
		log.Fatalf("не удалось открыть соединение с БД: %v", err)
	}
	defer db.Close()

	if err := db.Ping(); err != nil {
		log.Fatalf("БД недоступна: %v", err)
	}

	// --- Композиция зависимостей (тот же принцип DI, что и в public/index.php) ---

	// Repository: конкретная реализация поверх Postgres, но дальше
	// используется только интерфейс repository.NotificationRepository.
	var notificationRepo repository.NotificationRepository = repository.NewPostgresNotificationRepository(db)

	// Strategy: список каналов доставки. Добавление нового канала —
	// это просто новая строка здесь, без изменения NotificationService.
	channels := []domain.Channel{
		strategy.NewLogChannel(),
		strategy.NewEmailChannel("notifications@order-system.local"),
	}

	notificationService := service.NewNotificationService(channels, notificationRepo)
	notificationHandler := handler.NewNotificationHandler(notificationService)

	// --- Роутинг ---
	mux := http.NewServeMux()
	mux.HandleFunc("/api/notifications/events", notificationHandler.HandleEvent)
	mux.HandleFunc("/api/notifications/logs", notificationHandler.ListLogs)
	mux.HandleFunc("/health", notificationHandler.Health)

	log.Printf("notification-service слушает на :%s", port)
	if err := http.ListenAndServe(":"+port, mux); err != nil {
		log.Fatalf("сервер остановлен с ошибкой: %v", err)
	}
}
