export const HOTEL_PLACEHOLDER_IMAGES = [
    'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1576013551627-0cc20b96c2a7?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1400&q=80',
] as const;

export const HOTEL_HERO_PLACEHOLDER = HOTEL_PLACEHOLDER_IMAGES[4];

export function hotelPlaceholderImage(seed: string | number = 0): string {
    const value = typeof seed === 'number' ? seed : Array.from(seed).reduce((sum, char) => sum + char.charCodeAt(0), 0);

    return HOTEL_PLACEHOLDER_IMAGES[Math.abs(value) % HOTEL_PLACEHOLDER_IMAGES.length];
}
