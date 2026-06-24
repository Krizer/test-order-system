package service

import (
	"log"
	"time"

	"notification-service/internal/domain"
	"notification-service/internal/repository"
)

// NotificationService — аналог Application Service из PHP-стороны.
// Получает зависимости через конструктор (Dependency Injection),
// сам не создаёт ни каналы, ни репозиторий.
//
// Обрабатывает событие через все доступные каналы (Strategy) и
// сохраняет факт обработки в репозиторий (Repository) — независимо
// от того, что хранение и доставка могут отказать по отдельности.
type NotificationService struct {
	channels []domain.Channel
	repo     repository.NotificationRepository
}

func NewNotificationService(channels []domain.Channel, repo repository.NotificationRepository) *NotificationService {
	return &NotificationService{
		channels: channels,
		repo:     repo,
	}
}

func (s *NotificationService) HandleEvent(event domain.Event) error {
	payload, err := repository.MarshalPayload(event.Data)
	if err != nil {
		return err
	}

	for _, channel := range s.channels {
		if sendErr := channel.Send(event); sendErr != nil {
			// Один канал не должен ронять обработку остальных —
			// тот же принцип изоляции ошибок, что и в PHP EventDispatcher.
			log.Printf("канал %s не смог обработать событие %s: %v", channel.Name(), event.Name, sendErr)
			continue
		}

		entry := domain.NotificationLog{
			EventName: event.Name,
			Channel:   channel.Name(),
			Payload:   payload,
			CreatedAt: time.Now(),
		}

		if err := s.repo.Save(entry); err != nil {
			log.Printf("не удалось сохранить лог нотификации для канала %s: %v", channel.Name(), err)
		}
	}

	return nil
}

func (s *NotificationService) GetRecentLogs() ([]domain.NotificationLog, error) {
	return s.repo.FindAll()
}
