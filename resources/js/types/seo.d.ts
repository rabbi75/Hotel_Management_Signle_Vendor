/*
|------------------------------------------------------------------------------
| SEO module
|------------------------------------------------------------------------------
|
| The mirror of app/Modules/SEO — SeoMetaResource, SeoReport and the sitemap
| status array. Any change on the PHP side must be reflected here.
|
*/

export type TwitterCard = 'summary' | 'summary_large_image' | 'app' | 'player';

/** The verdict of one check. `warn` exists so advisory findings are not failures. */
export type SeoCheckStatus = 'pass' | 'warn' | 'fail';

export interface SeoCheck {
    key: string;
    label: string;
    status: SeoCheckStatus;
    /** Names the problem *and* the fix. Render it verbatim. */
    message: string;
    weight: number;
}

export interface SeoReport {
    score: number;
    passed: number;
    warnings: number;
    failed: number;
    checks: SeoCheck[];
}

/**
 * A stored override. Every field is nullable: null means "inherit the workspace
 * default", which the panel shows as placeholder text.
 */
export interface SeoMetaPayload {
    title: string | null;
    description: string | null;
    keywords: string | null;
    canonical_url: string | null;
    robots_index: boolean;
    robots_follow: boolean;
    og_title: string | null;
    og_description: string | null;
    og_image: string | null;
    twitter_card: TwitterCard;
    twitter_title: string | null;
    twitter_description: string | null;
    twitter_image: string | null;
    structured_data: Record<string, unknown> | null;
}

/**
 * What the editor form binds to.
 *
 * `structured_data` is excluded deliberately: it is generated server-side per
 * content type, is not editable in the panel, and its `unknown` values do not
 * satisfy Inertia's serialisable-form constraint.
 */
export type SeoPanelValue = Omit<SeoMetaPayload, 'structured_data'>;

/** Limits and defaults the panel needs to render counters and the preview. */
export interface SeoContext {
    title_max: number;
    description_max: number;
    title_suffix: string | null;
    indexable: boolean;
    base_url: string;
}

export interface SeoDefaults {
    title: string | null;
    description: string | null;
    title_suffix: string | null;
    title_max: number;
    description_max: number;
    og_image: string | null;
    twitter_handle: string | null;
    indexable: boolean;
}

export interface SitemapStatus {
    path: string;
    url: string;
    exists: boolean;
    generated_at: string | null;
    url_count: number | null;
    indexable: boolean;
}

export interface SeoContentRow {
    type: string;
    type_label: string;
    id: number;
    title: string;
    score: number;
    failed: number;
    has_meta: boolean;
}

export interface SeoSettingsValues {
    default_title: string | null;
    default_description: string | null;
    title_suffix: string | null;
    title_max: number;
    description_max: number;
    default_og_image: string | null;
    twitter_handle: string | null;
    robots_indexable: boolean;
}
