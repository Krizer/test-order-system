package strategy

import (
	"fmt"
	"log"

	"notification-service/internal/domain"
)

// LogChannel — простейшая реализация Channel: просто пишет в лог сервиса.
// В реальном проекте сюда можно добавить EmailChannel, SlackChannel,
// SMSChannel — каждый со своей реализацией Send(), без изменения
// кода, который их вызывает (NotificationService).
type LogChannel struct{}

func NewLogChannel() *LogChannel {
	return &LogChannel{}
}

func (c *LogChannel) Name() string {
	return "log"
}

func (c *LogChannel) Send(event domain.Event) error {
	log.Printf("[notification] событие=%s данные=%v\n", event.Name, event.Data)
	return nil
}

// EmailChannel — заготовка под реальную отправку email.
// Сейчас лишь имитирует отправку, чтобы показать, как легко
// добавить новый канал без изменения остального кода (Open/Closed Principle).
type EmailChannel struct {
	fromAddress string
}

func NewEmailChannel(fromAddress string) *EmailChannel {
	return &EmailChannel{fromAddress: fromAddress}
}

func (c *EmailChannel) Name() string {
	return "email"
}

func (c *EmailChannel) Send(event domain.Event) error {
	customerID, _ := event.Data["customer_id"]
	fmt.Printf(
		"[email от %s] Уведомление по событию %s для клиента %v\n",
		c.fromAddress, event.Name, customerID,
	)
	return nil
}
