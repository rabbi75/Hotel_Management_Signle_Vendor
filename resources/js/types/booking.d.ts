export interface PublicHotelSummary {
    name: string;
    slug: string;
    description: string | null;
    city: string | null;
    country: string | null;
    currency: string;
    check_in_time: string;
    check_out_time: string;
    phone: string | null;
    email: string | null;
    cover: string | null;
}

export interface PublicCatalogRoom {
    room_type_id: number;
    name: string;
    code: string | null;
    description: string | null;
    bed_configuration: string | null;
    max_adults: number;
    max_children: number;
    max_occupancy: number;
    image: string | null;
    facilities: string[];
    nightly_rate: number;
    nights: number;
    subtotal: number;
    currency: string;
    available_rooms: number;
}

export interface PublicAvailabilityRow {
    room_type_id: number;
    room_type: string;
    code: string | null;
    available_rooms: number;
    nightly_rate: number;
    nights: number;
    subtotal: number;
    currency: string;
}

export interface PublicBookingFilters {
    check_in_date: string | null;
    check_out_date: string | null;
    adults: number;
    children: number;
    room_type_id: number | null;
}

export interface PublicBookingConfirmation {
    number: string;
    status: string;
    status_label: string;
    check_in_date: string;
    check_out_date: string;
    adults: number;
    children: number;
    total: number;
    currency: string;
    room_type: string | null;
    guest_name: string | null;
    guest_email: string | null;
}
