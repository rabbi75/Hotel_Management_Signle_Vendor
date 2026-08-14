import { hotelPlaceholderImage } from '@/lib/hotel-placeholders';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface HotelPhotoProps {
    src?: string | null;
    alt?: string;
    seed?: string | number;
    className?: string;
    decorative?: boolean;
}

export function HotelPhoto({ src, alt = '', seed = 0, className, decorative = false }: HotelPhotoProps) {
    const fallback = hotelPlaceholderImage(seed);
    const [url, setUrl] = useState(src && src !== '' ? src : fallback);

    return (
        <img
            src={url}
            alt={decorative ? '' : alt}
            aria-hidden={decorative || undefined}
            loading="lazy"
            className={cn('size-full object-cover', className)}
            onError={() => {
                if (url !== fallback) {
                    setUrl(fallback);
                }
            }}
        />
    );
}
