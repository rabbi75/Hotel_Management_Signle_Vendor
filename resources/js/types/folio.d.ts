export type OptionMap = Record<string, string>;

export interface HotelServiceRow {
    id: number;
    hotel_id: number | null;
    hotel: string | null;
    name: string;
    code: string | null;
    category: string;
    category_label: string;
    price: number;
    tax_rate: number;
    description: string | null;
    is_active: boolean;
    created_at: string | null;
}

export interface FolioItemRow {
    id: number;
    type: string;
    type_label: string;
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
    tax_amount: number;
    hotel_service_id: number | null;
    posted_at: string | null;
}

export interface GuestPaymentRow {
    id: number;
    amount: number;
    currency: string;
    method: string;
    method_label: string;
    status: string;
    status_label: string;
    reference: string | null;
    notes: string | null;
    paid_at: string | null;
}

export interface GuestFolioRow {
    id: number;
    number: string;
    hotel_id: number;
    hotel: string | null;
    guest_id: number;
    guest: string | null;
    reservation_id: number | null;
    reservation: string | null;
    status: string;
    status_label: string;
    status_color: string;
    currency: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    paid_amount: number;
    balance: number;
    opened_at: string | null;
    closed_at: string | null;
    notes: string | null;
    is_open: boolean;
    items: FolioItemRow[];
    payments: GuestPaymentRow[];
    invoice_id: number | null;
    invoice_number: string | null;
    created_at: string | null;
}

export interface GuestInvoiceRow {
    id: number;
    number: string;
    guest_id: number;
    guest: string | null;
    reservation_id: number | null;
    reservation: string | null;
    guest_folio_id: number;
    folio: string | null;
    status: string;
    status_label: string;
    status_color: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    currency: string;
    issued_at: string | null;
    paid_at: string | null;
    created_at: string | null;
}

export interface EnumOption {
    value: string;
    label: string;
}
