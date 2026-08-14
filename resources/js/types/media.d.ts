/*
|------------------------------------------------------------------------------
| Media library
|------------------------------------------------------------------------------
|
| Mirrors App\Modules\Media\Http\Resources\* and the props MediaController
| attaches alongside them.
|
*/

export type MediaTypeValue = 'image' | 'video' | 'audio' | 'document' | 'other';

export interface MediaAsset {
    id: number;
    name: string;
    title: string | null;
    alt: string | null;
    caption: string | null;
    tags: string[];
    folder_id: number | null;
    folder: string | null;
    type: MediaTypeValue;
    type_label: string;
    type_color: string;
    mime_type: string;
    extension: string;
    size: number;
    size_human: string;
    width: number | null;
    height: number | null;
    url: string | null;
    thumb_url: string | null;
    preview_url: string | null;
    uploaded_by: number | null;
    uploader: string | null;
    original_asset_id: number | null;
    versions_count: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface MediaFolder {
    id: number;
    name: string;
    slug: string;
    path: string;
    parent_id: number | null;
    depth: number;
    assets_count: number | null;
    children_count: number | null;
    children: MediaFolder[];
    created_at: string | null;
}

export interface MediaPageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface MediaFilters {
    search: string | null;
    type: string | null;
    uploader: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: 'asc' | 'desc';
}

export interface MediaLimits {
    max_upload_kb: number;
    extensions: string[];
    per_page: number;
}

export interface MediaTypeOption {
    value: MediaTypeValue;
    label: string;
}

export interface MediaUploaderOption {
    value: string;
    label: string;
}

export interface MediaBreadcrumbEntry {
    id: number;
    name: string;
}

/** Client-side upload progress; never sent by the server. */
export interface MediaUploadTask {
    id: string;
    name: string;
    size: number;
    progress: number;
    status: 'queued' | 'uploading' | 'done' | 'error';
    error: string | null;
}
