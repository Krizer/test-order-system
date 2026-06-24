package domain

import "time"

// Event — событие, пришедшее по HTTP webhook от PHP Orders Service.
// Это "входная точка" Observer-паттерна на стороне Go: PHP не знает,
// что Go делает с этим событием, а Go не знает деталей домена заказов —
// только то, что прислали в JSON.
type Event struct {
	Name string                 `json:"event"`
	Data map[string]interface{} `json:"data"`
}

// NotificationLog — запись о том, что уведомление было обработано.
// Сохраняется в собственную таблицу нотификаций (своя БД-зона сервиса,
// отдельная от orders/order_items, которыми владеет PHP-сервис).
type NotificationLog struct {
	ID        int64
	EventName string
	Channel   string
	Payload   string
	CreatedAt time.Time
}

// Channel — паттерн Strategy на стороне Go: разные каналы уведомлений
// (лог, email, slack-webhook и т.п.) реализуют один и тот же интерфейс.
// NotificationService не знает деталей конкретного канала — только
// то, что у него есть метод Send.
type Channel interface {
	Name() string
	Send(event Event) error
}
