export type OptionMap = Record<string, string>;

export interface ReportFilters {
    from: string | null;
    to: string | null;
    hotel_id: string | null;
}

export interface RoomStatusSlice {
    status: string;
    label: string;
    count: number;
    color: string;
}

export interface HotelDashboardSnapshot {
    hotel_id: number | null;
    date: string;
    total_rooms: number;
    occupied_rooms: number;
    available_rooms: number;
    occupancy_rate: number;
    in_house_guests: number;
    arrivals_today: number;
    departures_today: number;
    revenue_today: number;
    currency: string;
    pending_housekeeping: number;
    open_maintenance: number;
    room_status_breakdown: RoomStatusSlice[];
}

export interface ArrivalRow {
    id: number;
    number: string;
    guest: string | null;
    room: string | null;
    hotel: string | null;
    date: string | null;
    status: string;
    status_label: string;
}

export interface OccupancyPoint {
    date: string;
    sold: number;
    total: number;
    rate: number;
}

export interface OccupancyReport {
    period: ReportFilters;
    total_rooms: number;
    average_occupancy: number;
    series: OccupancyPoint[];
}

export interface RevenuePoint {
    date: string;
    amount: number;
}

export interface RevenueReport {
    period: ReportFilters;
    currency: string;
    total: number;
    series: RevenuePoint[];
}

export interface OperationsReport {
    period: ReportFilters;
    arrivals: ArrivalRow[];
    departures: ArrivalRow[];
    summary: {
        arrivals_count: number;
        departures_count: number;
    };
}
