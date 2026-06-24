package handler

import (
	"encoding/json"
	"net/http"

	"notification-service/internal/domain"
	"notification-service/internal/service"
)

// NotificationHandler — HTTP-слой. Как и OrderController в PHP,
// занимается только переводом между HTTP и сервисным слоем —
// никакой бизнес-логики здесь нет.
type NotificationHandler struct {
	service *service.NotificationService
}

func NewNotificationHandler(svc *service.NotificationService) *NotificationHandler {
	return &NotificationHandler{service: svc}
}

// HandleEvent принимает webhook от PHP Orders Service: POST /api/notifications/events
func (h *NotificationHandler) HandleEvent(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]string{"error": "метод не поддерживается"})
		return
	}

	var event domain.Event
	if err := json.NewDecoder(r.Body).Decode(&event); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "некорректный JSON"})
		return
	}

	if event.Name == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "поле event обязательно"})
		return
	}

	if err := h.service.HandleEvent(event); err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	writeJSON(w, http.StatusAccepted, map[string]string{"status": "accepted"})
}

// ListLogs отдаёт последние обработанные нотификации: GET /api/notifications/logs
func (h *NotificationHandler) ListLogs(w http.ResponseWriter, r *http.Request) {
	logs, err := h.service.GetRecentLogs()
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	writeJSON(w, http.StatusOK, logs)
}

func (h *NotificationHandler) Health(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]string{"status": "ok", "service": "notification-service"})
}

func writeJSON(w http.ResponseWriter, statusCode int, payload interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(statusCode)
	_ = json.NewEncoder(w).Encode(payload)
}
