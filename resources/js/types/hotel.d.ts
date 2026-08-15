export interface EnumOption {
    value: string;
    label: string;
    color: string;
}

export type OptionMap = Record<string, string>;

export interface HotelRow {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    check_in_time: string;
    check_out_time: string;
    currency: string;
    timezone: string;
    tax_rate: number;
    tax_name: string | null;
    policies: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    status: string;
    status_label: string;
    status_color: string;
    is_active: boolean;
    logo: string | null;
    cover: string | null;
    rooms_count: number | null;
    created_at: string | null;
}

export interface BuildingRow {
    id: number;
    hotel_id: number;
    hotel: string | null;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    floors_count: number | null;
    created_at: string | null;
}

export interface FloorRow {
    id: number;
    hotel_id: number;
    hotel: string | null;
    building_id: number | null;
    building: string | null;
    name: string;
    floor_number: number;
    code: string | null;
    description: string | null;
    is_active: boolean;
    rooms_count: number | null;
    created_at: string | null;
}

export interface RoomTypeRow {
    id: number;
    hotel_id: number;
    hotel: string | null;
    name: string;
    code: string | null;
    description: string | null;
    base_price: number;
    max_adults: number;
    max_children: number;
    max_occupancy: number;
    bed_configuration: string | null;
    is_active: boolean;
    facility_ids: number[];
    rooms_count: number | null;
    created_at: string | null;
}

export interface RoomRow {
    id: number;
    hotel_id: number;
    hotel: string | null;
    building_id: number | null;
    building: string | null;
    floor_id: number | null;
    floor: string | null;
    room_type_id: number | null;
    room_type: string | null;
    number: string;
    code: string | null;
    description: string | null;
    base_price: number | null;
    max_occupancy: number | null;
    status: string;
    status_label: string;
    status_color: string;
    is_active: boolean;
    facility_ids: number[];
    image: string | null;
    image_thumb: string | null;
    beds_count: number | null;
    created_at: string | null;
}

export interface BedRow {
    id: number;
    hotel_id: number;
    hotel: string | null;
    room_id: number;
    room: string | null;
    floor_id: number | null;
    name: string;
    code: string | null;
    bed_type: string | null;
    price: number;
    description: string | null;
    status: string;
    status_label: string;
    status_color: string;
    is_active: boolean;
    created_at: string | null;
}

export interface FacilityRow {
    id: number;
    hotel_id: number | null;
    hotel: string | null;
    name: string;
    code: string | null;
    description: string | null;
    icon: string | null;
    is_active: boolean;
    created_at: string | null;
}
