import { cn } from '@/lib/utils';
import {
    Activity,
    ArrowDownToLine,
    ArrowUpRight,
    Banknote,
    BedDouble,
    Bell,
    Blocks,
    Book,
    Boxes,
    Building2,
    CalendarDays,
    ChartColumn,
    ChartPie,
    Check,
    CircleAlert,
    CircleCheck,
    CircleHelp,
    Cog,
    CreditCard,
    Database,
    FileText,
    Folder,
    Gauge,
    Globe,
    History,
    House,
    Inbox,
    Info,
    Key,
    KeyRound,
    Layers,
    LayoutDashboard,
    LifeBuoy,
    Link2,
    ListChecks,
    Lock,
    LogOut,
    Mail,
    Megaphone,
    MessageSquare,
    Monitor,
    Network,
    Package,
    Palette,
    Paperclip,
    Plug,
    Plus,
    Receipt,
    RefreshCw,
    ScrollText,
    Search,
    Send,
    Settings,
    Shapes,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Star,
    Tag,
    Trash2,
    TrendingUp,
    Upload,
    User,
    UserPlus,
    UserRound,
    Users,
    UsersRound,
    Wallet,
    Webhook,
    Workflow,
    Zap,
    type LucideIcon,
    type LucideProps,
} from 'lucide-react';
import { lazy, Suspense, type ComponentType } from 'react';

/**
 * The icons the navigation and command registry actually reference, keyed by
 * the kebab-case name the server sends.
 *
 * Naming them explicitly is what lets Rollup tree-shake the rest of the icon
 * set out of the entry chunk; a barrel re-export or a `lucide-react/*` index
 * lookup would pull all ~1,500 icons into the initial bundle.
 */
const ICONS: Record<string, LucideIcon> = {
    activity: Activity,
    'arrow-down-to-line': ArrowDownToLine,
    'arrow-up-right': ArrowUpRight,
    banknote: Banknote,
    'bed-double': BedDouble,
    bell: Bell,
    blocks: Blocks,
    book: Book,
    boxes: Boxes,
    'building-2': Building2,
    'calendar-days': CalendarDays,
    'chart-column': ChartColumn,
    'chart-pie': ChartPie,
    check: Check,
    'circle-alert': CircleAlert,
    'circle-check': CircleCheck,
    'circle-help': CircleHelp,
    cog: Cog,
    'credit-card': CreditCard,
    database: Database,
    'file-text': FileText,
    folder: Folder,
    gauge: Gauge,
    globe: Globe,
    history: History,
    house: House,
    inbox: Inbox,
    info: Info,
    key: Key,
    'key-round': KeyRound,
    layers: Layers,
    'layout-dashboard': LayoutDashboard,
    'life-buoy': LifeBuoy,
    'link-2': Link2,
    'list-checks': ListChecks,
    lock: Lock,
    'log-out': LogOut,
    mail: Mail,
    megaphone: Megaphone,
    'message-square': MessageSquare,
    monitor: Monitor,
    network: Network,
    package: Package,
    palette: Palette,
    paperclip: Paperclip,
    plug: Plug,
    plus: Plus,
    receipt: Receipt,
    'refresh-cw': RefreshCw,
    'scroll-text': ScrollText,
    search: Search,
    send: Send,
    settings: Settings,
    shapes: Shapes,
    'shield-alert': ShieldAlert,
    'shield-check': ShieldCheck,
    'shopping-cart': ShoppingCart,
    sparkles: Sparkles,
    star: Star,
    tag: Tag,
    'trash-2': Trash2,
    'trending-up': TrendingUp,
    upload: Upload,
    user: User,
    'user-plus': UserPlus,
    'user-round': UserRound,
    users: Users,
    'users-round': UsersRound,
    wallet: Wallet,
    webhook: Webhook,
    workflow: Workflow,
    zap: Zap,
};

/** The fallback for names outside the map: its own async chunk, never in the entry bundle. */
const DynamicIcon = lazy(async () => {
    const module = await import('lucide-react/dynamic');

    return { default: module.DynamicIcon as unknown as ComponentType<LucideProps & { name: string }> };
});

export function isKnownIcon(name: string | null | undefined): boolean {
    return Boolean(name && name in ICONS);
}

/** Resolves a mapped icon synchronously; returns `null` for anything else. */
export function resolveIcon(name: string | null | undefined): LucideIcon | null {
    if (!name) {
        return null;
    }

    return ICONS[name] ?? null;
}

export interface IconProps extends Omit<LucideProps, 'ref' | 'name'> {
    /** Kebab-case lucide name, e.g. `users-round`. */
    name: string | null | undefined;
    /** Rendered when the name is absent or unresolvable. */
    fallback?: LucideIcon;
}

export function Icon({ name, fallback: Fallback = Shapes, className, ...props }: IconProps) {
    const classes = cn('size-4 shrink-0', className);
    const Mapped = resolveIcon(name);

    if (Mapped) {
        return <Mapped aria-hidden="true" className={classes} {...props} />;
    }

    if (!name) {
        return <Fallback aria-hidden="true" className={classes} {...props} />;
    }

    return (
        <Suspense fallback={<Fallback aria-hidden="true" className={classes} {...props} />}>
            <DynamicIcon name={name} aria-hidden="true" className={classes} {...props} />
        </Suspense>
    );
}
