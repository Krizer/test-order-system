import { useState, type FormEvent } from 'react';
import { ordersApi } from '../api/client';
import type { CustomerTier, Order } from '../types/order';

/**
 * Черновик позиции заказа в форме. quantity/unit_price хранятся как
 * строки, потому что это значения из <input>, а не доменные числа —
 * приводим к number только при отправке (см. handleSubmit).
 */
interface DraftItem {
  product_name: string;
  quantity: string;
  unit_price: string;
}

const EMPTY_ITEM: DraftItem = { product_name: '', quantity: '1', unit_price: '0' };

const TIER_HINTS: Record<CustomerTier, string> = {
  regular: 'Без скидки',
  vip: 'Скидка 15% от суммы заказа',
  wholesale: 'Скидка 30% при заказе от 500 ₸',
};

interface CreateOrderFormProps {
  onCreated: (order: Order) => void;
}

export function CreateOrderForm({ onCreated }: CreateOrderFormProps) {
  const [customerId, setCustomerId] = useState('');
  const [customerTier, setCustomerTier] = useState<CustomerTier>('regular');
  const [items, setItems] = useState<DraftItem[]>([{ ...EMPTY_ITEM }]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function updateItem(index: number, field: keyof DraftItem, value: string) {
    setItems((prev) =>
      prev.map((item, i) => (i === index ? { ...item, [field]: value } : item))
    );
  }

  function addItem() {
    setItems((prev) => [...prev, { ...EMPTY_ITEM }]);
  }

  function removeItem(index: number) {
    setItems((prev) => prev.filter((_, i) => i !== index));
  }

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const order = await ordersApi.create({
        customer_id: Number(customerId),
        customer_tier: customerTier,
        items: items.map((item) => ({
          product_name: item.product_name,
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
        })),
      });

      onCreated(order);
      setCustomerId('');
      setCustomerTier('regular');
      setItems([{ ...EMPTY_ITEM }]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось создать заказ');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="panel" onSubmit={handleSubmit}>
      <h2 className="panel__title">Новый заказ</h2>

      <div className="field-row">
        <label className="field">
          <span className="field__label">ID клиента</span>
          <input
            type="number"
            required
            min="1"
            value={customerId}
            onChange={(e) => setCustomerId(e.target.value)}
            placeholder="42"
          />
        </label>

        <label className="field">
          <span className="field__label">Тип клиента</span>
          <select
            value={customerTier}
            onChange={(e) => setCustomerTier(e.target.value as CustomerTier)}
          >
            <option value="regular">regular</option>
            <option value="vip">vip</option>
            <option value="wholesale">wholesale</option>
          </select>
        </label>
      </div>

      <p className="hint">{TIER_HINTS[customerTier]}</p>

      <div className="items">
        <span className="field__label">Позиции заказа</span>

        {items.map((item, index) => (
          <div className="item-row" key={index}>
            <input
              type="text"
              placeholder="Название товара"
              required
              value={item.product_name}
              onChange={(e) => updateItem(index, 'product_name', e.target.value)}
            />
            <input
              type="number"
              min="1"
              placeholder="Кол-во"
              required
              value={item.quantity}
              onChange={(e) => updateItem(index, 'quantity', e.target.value)}
            />
            <input
              type="number"
              min="0"
              step="0.01"
              placeholder="Цена"
              required
              value={item.unit_price}
              onChange={(e) => updateItem(index, 'unit_price', e.target.value)}
            />
            {items.length > 1 && (
              <button
                type="button"
                className="icon-button"
                onClick={() => removeItem(index)}
                aria-label="Удалить позицию"
              >
                ✕
              </button>
            )}
          </div>
        ))}

        <button type="button" className="link-button" onClick={addItem}>
          + Добавить позицию
        </button>
      </div>

      {error && <p className="error">{error}</p>}

      <button type="submit" className="primary-button" disabled={submitting}>
        {submitting ? 'Создаём…' : 'Создать заказ'}
      </button>
    </form>
  );
}
