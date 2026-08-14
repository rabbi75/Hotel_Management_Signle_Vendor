import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTheme } from '@/hooks/use-theme';
import type { Appearance } from '@/types';
import { Monitor, Moon, Sun } from 'lucide-react';

const OPTIONS: { value: Appearance; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

export interface ThemeToggleProps {
    className?: string;
    align?: 'start' | 'center' | 'end';
}

export function ThemeToggle({ className, align = 'end' }: ThemeToggleProps) {
    const { appearance, setAppearance } = useTheme();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className={className} aria-label={`Theme: ${appearance}`}>
                    <Sun className="size-4 dark:hidden" aria-hidden="true" />
                    <Moon className="hidden size-4 dark:block" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="w-40">
                <DropdownMenuRadioGroup value={appearance} onValueChange={(value) => setAppearance(value as Appearance)}>
                    {OPTIONS.map(({ value, label, icon: OptionIcon }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <OptionIcon className="size-4 opacity-70" aria-hidden="true" />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
