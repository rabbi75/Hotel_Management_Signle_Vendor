import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { TableColumn } from '@/types';
import { Download, FileSpreadsheet, FileText, Printer, RotateCcw, Settings2 } from 'lucide-react';

export type ExportFormat = 'csv' | 'excel' | 'print';

export interface DataTableViewOptionsProps {
    columns: TableColumn[];
    isColumnVisible: (key: string) => boolean;
    onToggleColumn: (key: string, visible: boolean) => void;
    onReset: () => void;
    onExport?: (format: ExportFormat) => void;
}

export function DataTableViewOptions({ columns, isColumnVisible, onToggleColumn, onReset, onExport }: DataTableViewOptionsProps) {
    const toggleable = columns.filter((column) => column.toggleable);

    return (
        <div className="flex items-center gap-2">
            {toggleable.length > 0 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm" className="h-9">
                            <Settings2 className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Columns</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-52">
                        <DropdownMenuLabel>Visible columns</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {toggleable.map((column) => (
                            <DropdownMenuCheckboxItem
                                key={column.key}
                                checked={isColumnVisible(column.key)}
                                onCheckedChange={(checked) => onToggleColumn(column.key, checked === true)}
                                onSelect={(event) => event.preventDefault()}
                            >
                                {column.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={onReset}>
                            <RotateCcw className="size-4 opacity-70" aria-hidden="true" />
                            Reset columns
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {onExport && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm" className="h-9">
                            <Download className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Export</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuLabel>Export current view</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => onExport('csv')}>
                            <FileText className="size-4 opacity-70" aria-hidden="true" />
                            CSV
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => onExport('excel')}>
                            <FileSpreadsheet className="size-4 opacity-70" aria-hidden="true" />
                            Excel
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => onExport('print')}>
                            <Printer className="size-4 opacity-70" aria-hidden="true" />
                            Print
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}
