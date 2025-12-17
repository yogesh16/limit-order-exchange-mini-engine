/**
 * Trading Types for the Limit Order Exchange
 */

// Order side enum values (backend returns string values)
export type OrderSide = 'buy' | 'sell';
export type OrderSideLabel = 'buy' | 'sell';

// Order status enum values (backend returns integer values)
// 1 = OPEN, 2 = FILLED, 3 = CANCELLED
export type OrderStatus = 1 | 2 | 3;
export type OrderStatusLabel = 'Open' | 'Filled' | 'Cancelled';

// Asset interface
export interface Asset {
    id: number;
    symbol: string;
    amount: string;
    locked_amount: string;
    total: string;
}

// Order interface
export interface Order {
    id: number;
    user_id: number;
    symbol: string;
    side: OrderSide;
    price: string;
    amount: string;
    status: OrderStatus;
    status_label: OrderStatusLabel;
    total: string;
    filled_at: string | null;
    created_at: string;
    updated_at: string;
}

// Trade interface
export interface Trade {
    id: number;
    symbol: string;
    price: string;
    amount: string;
    commission: string;
    executed_at: string;
}

// Profile interface
export interface Profile {
    id: number;
    name: string;
    email: string;
    balance: string;
    assets: Asset[];
    open_orders: Order[];
    created_at: string;
}

// OrderMatched event payload
export interface OrderMatchedPayload {
    trade: Trade;
    buyer_id: number;
    seller_id: number;
}

// Orderbook types
export interface OrderbookOrder {
    price: string;
    amount: string;
    total: string;
}

export interface Orderbook {
    bids: OrderbookOrder[]; // Buy orders (highest first)
    asks: OrderbookOrder[]; // Sell orders (lowest first)
}

// Request types
export interface CreateOrderRequest {
    symbol: string;
    side: OrderSideLabel;
    price: number;
    amount: number;
}

// API Response types
export interface ApiResponse<T> {
    data: T;
    message?: string;
}

export interface ApiErrorResponse {
    message: string;
    errors?: Record<string, string[]>;
}
