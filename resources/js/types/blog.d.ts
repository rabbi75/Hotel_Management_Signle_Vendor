import type { SeoMetaPayload } from '@/types/seo';

/*
|------------------------------------------------------------------------------
| Blog module
|------------------------------------------------------------------------------
|
| The mirror of app/Modules/Blog's HTTP resources.
|
*/

export type PostStatus = 'draft' | 'scheduled' | 'published' | 'archived';
export type BodyFormat = 'markdown' | 'html';
export type CommentStatus = 'pending' | 'approved' | 'spam';

/** The shape App\Support\Enums\Concerns\HasLabel::options() produces. */
export interface EnumOption {
    value: string | number;
    label: string;
    color: string;
}

/** A picker option. A list, not an id-keyed map — see ProvidesBlogOptions. */
export interface BlogOption {
    value: string;
    label: string;
}

export interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    /** What the author typed. Never render this directly. */
    body: string | null;
    /** Server-sanitised HTML — the only form safe to inject. */
    body_html: string | null;
    body_format: BodyFormat;
    status: PostStatus;
    status_label: string;
    status_color: string;
    published_at: string | null;
    is_published: boolean;
    featured_image: string | null;
    reading_time: number;
    view_count: number;
    is_featured: boolean;
    allow_comments: boolean;
    url: string | null;
    author_id: number | null;
    author: string | null;
    category_id: number | null;
    category: string | null;
    tag_ids: number[];
    tags: string[];
    comments_count: number | null;
    seo: SeoMetaPayload | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface BlogCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    parent: string | null;
    posts_count: number | null;
    children: BlogCategory[];
    created_at: string | null;
}

export interface BlogTag {
    id: number;
    name: string;
    slug: string;
    posts_count: number | null;
    created_at: string | null;
}

export interface BlogComment {
    id: number;
    post_id: number;
    post: string | null;
    parent_id: number | null;
    author: string;
    author_email: string | null;
    is_guest: boolean;
    /** Plain text. Comments have no markup path; never render as HTML. */
    body: string;
    status: CommentStatus;
    status_label: string;
    status_color: string;
    ip_address: string | null;
    replies: BlogComment[];
    created_at: string | null;
}

export interface PublicArchive {
    type: 'category' | 'tag';
    name: string;
    description: string | null;
}

export interface PublicPostPage {
    data: Post[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface PublicTaxonomy {
    id: number;
    name: string;
    slug: string;
}
