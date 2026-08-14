/*
|------------------------------------------------------------------------------
| CMS
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\CMS\Http\Resources\* plus the block schema payload that
| App\Modules\CMS\Services\BlockRegistry serialises for the editor.
|
*/

import type { TablePayload } from './index';

export type PageStatus = 'draft' | 'published' | 'scheduled';
export type MenuLocation = 'header' | 'footer' | 'sidebar';
export type LinkTarget = '_self' | '_blank';

export interface PageSeo {
    title?: string;
    description?: string;
    keywords?: string;
    og_image?: string;
    canonical?: string;
    noindex?: boolean;
    /** Present so the panel can be submitted as a nested form payload. */
    [key: string]: string | boolean | undefined;
}

export interface PageBlockRow {
    id: number;
    page_id: number;
    type: string;
    label: string;
    icon: string;
    order: number;
    is_visible: boolean;
    data: BlockData;
}

export interface PageRow {
    id: number;
    title: string;
    slug: string;
    path: string;
    status: PageStatus;
    status_label: string;
    status_color: string;
    layout: string;
    parent_id: number | null;
    parent: string | null;
    seo: PageSeo;
    is_homepage: boolean;
    is_public: boolean;
    author: string | null;
    blocks_count: number | null;
    blocks: PageBlockRow[];
    published_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/*
| Block schemas
*/

export type BlockFieldType = 'text' | 'textarea' | 'richtext' | 'url' | 'image' | 'boolean' | 'number' | 'select' | 'repeater';

export interface BlockFieldOption {
    value: string;
    label: string;
}

/**
 * Which panel of the block editor a field belongs to: its own content, or the
 * layout options every block shares.
 */
export type BlockFieldGroup = 'content' | 'section';

export interface BlockFieldSchema {
    name: string;
    label: string;
    type: BlockFieldType;
    group: BlockFieldGroup;
    default: BlockValue;
    help: string | null;
    placeholder: string | null;
    required: boolean;
    options: BlockFieldOption[];
    fields: BlockFieldSchema[];
}

export interface BlockSchema {
    type: string;
    label: string;
    icon: string;
    description: string;
    defaults: BlockData;
    fields: BlockFieldSchema[];
}

/** A single stored value inside a block's `data` payload. */
export type BlockValue = string | number | boolean | null | BlockData[] | string[];

export type BlockData = Record<string, BlockValue>;

/** A row inside a repeater field. */
export type BlockRepeaterRow = Record<string, BlockValue>;

/*
| Rendered output
*/

export interface RenderedBlock {
    id: number;
    type: string;
    order: number;
    is_visible: boolean;
    data: BlockData;
}

export interface RenderedSeo {
    title: string;
    description: string;
    keywords: string;
    og_image: string;
    canonical: string;
    noindex: boolean;
}

export interface RenderedPage {
    id: number;
    title: string;
    slug: string;
    layout: string;
    status: PageStatus;
    seo: RenderedSeo;
    blocks: RenderedBlock[];
    published_at: string | null;
}

/*
| Menus
*/

export interface MenuItemRow {
    id: number;
    menu_id: number;
    parent_id: number | null;
    page_id: number | null;
    label: string;
    url: string | null;
    resolved_url: string;
    target: LinkTarget;
    icon: string | null;
    permission: string | null;
    order: number;
    children: MenuItemRow[];
}

export interface MenuRow {
    id: number;
    name: string;
    location: MenuLocation;
    location_label: string;
    items: MenuItemRow[];
    created_at: string | null;
}

export interface PublicMenuNode {
    id: number;
    label: string;
    url: string;
    target: LinkTarget;
    icon: string | null;
    permission: string | null;
    children: PublicMenuNode[];
}

/*
| Page props
*/

export type OptionMap = Record<string, string>;

export interface EnumOptionRow {
    value: string | number;
    label: string;
    color: string;
}

export interface PagesIndexProps {
    table: TablePayload<PageRow>;
    can: { create: boolean; publish: boolean; delete: boolean };
    [key: string]: unknown;
}

export interface PageEditorProps {
    page?: PageRow;
    parents: OptionMap;
    blockTypes: BlockSchema[];
    reservedSlugs: string[];
    can?: { publish: boolean; delete: boolean };
    [key: string]: unknown;
}

export interface MenusIndexProps {
    menus: MenuRow[];
    locations: EnumOptionRow[];
    targets: EnumOptionRow[];
    pages: OptionMap;
    can: { manage: boolean };
    [key: string]: unknown;
}

export interface PublicPageProps {
    page: RenderedPage;
    preview: boolean;
    menus?: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
    expiresIn?: number;
    [key: string]: unknown;
}
