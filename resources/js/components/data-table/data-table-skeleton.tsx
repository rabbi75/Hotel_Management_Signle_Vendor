import { Skeleton } from '@/components/ui/skeleton';
import { TableBody, TableCell, TableRow } from '@/components/ui/table';

export interface DataTableSkeletonProps {
    rows?: number;
    columns: number;
}

/** Body-only skeleton so the header and toolbar stay in place during a reload. */
export function DataTableSkeleton({ rows = 8, columns }: DataTableSkeletonProps) {
    return (
        <TableBody aria-busy="true">
            {Array.from({ length: rows }, (_, rowIndex) => (
                <TableRow key={rowIndex}>
                    {Array.from({ length: columns }, (_, columnIndex) => (
                        <TableCell key={columnIndex}>
                            <Skeleton className="h-4" style={{ width: `${55 + ((rowIndex * 7 + columnIndex * 13) % 40)}%` }} />
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </TableBody>
    );
}

/** Card-shaped skeleton for the stacked mobile layout. */
export function DataTableCardSkeleton({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-3" aria-busy="true">
            {Array.from({ length: rows }, (_, index) => (
                <div key={index} className="space-y-2 rounded-lg border border-border p-4">
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="h-3 w-2/3" />
                    <Skeleton className="h-3 w-1/2" />
                </div>
            ))}
        </div>
    );
}
