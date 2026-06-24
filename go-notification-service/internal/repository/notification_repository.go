package repository

import (
	"database/sql"
	"encoding/json"
	"time"

	"notification-service/internal/domain"
)

// NotificationRepository — интерфейс хранения логов нотификаций.
// Как и в PHP-сервисе, остальной код знает только об этом контракте,
// а не о конкретной БД (паттерн Repository).
type NotificationRepository interface {
	Save(log domain.NotificationLog) error
	FindAll() ([]domain.NotificationLog, error)
}

// PostgresNotificationRepository — конкретная реализация поверх PostgreSQL.
type PostgresNotificationRepository struct {
	db *sql.DB
}

func NewPostgresNotificationRepository(db *sql.DB) *PostgresNotificationRepository {
	return &PostgresNotificationRepository{db: db}
}

func (r *PostgresNotificationRepository) Save(entry domain.NotificationLog) error {
	query := `
		INSERT INTO notification_logs (event_name, channel, payload, created_at)
		VALUES ($1, $2, $3, $4)
	`

	_, err := r.db.Exec(query, entry.EventName, entry.Channel, entry.Payload, entry.CreatedAt)
	return err
}

func (r *PostgresNotificationRepository) FindAll() ([]domain.NotificationLog, error) {
	rows, err := r.db.Query(`
		SELECT id, event_name, channel, payload, created_at
		FROM notification_logs
		ORDER BY created_at DESC
		LIMIT 100
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var logs []domain.NotificationLog
	for rows.Next() {
		var entry domain.NotificationLog
		if err := rows.Scan(&entry.ID, &entry.EventName, &entry.Channel, &entry.Payload, &entry.CreatedAt); err != nil {
			return nil, err
		}
		logs = append(logs, entry)
	}

	return logs, rows.Err()
}

// MarshalPayload — вспомогательная функция, переводящая данные события
// в JSON-строку перед сохранением в текстовую колонку payload.
func MarshalPayload(data map[string]interface{}) (string, error) {
	bytes, err := json.Marshal(data)
	if err != nil {
		return "", err
	}
	return string(bytes), nil
}

var _ = time.Now // используется в domain.NotificationLog при создании записи
