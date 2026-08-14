import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import type { MediaAsset } from '@/types/media';
import { useForm } from '@inertiajs/react';
import { Info, RotateCcw, RotateCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useMediaPanel } from './use-panel';

export interface EditorDialogProps {
    asset: MediaAsset | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface EditorValues {
    rotate: number;
    crop_x: string;
    crop_y: string;
    crop_width: string;
    crop_height: string;
    width: string;
    height: string;
    quality: number;
    [key: string]: string | number;
}

function emptyValues(): EditorValues {
    return {
        rotate: 0,
        crop_x: '',
        crop_y: '',
        crop_width: '',
        crop_height: '',
        width: '',
        height: '',
        quality: 90,
    };
}

/**
 * Crop, rotate and resize.
 *
 * The preview is rotated with CSS only — the server does the real work, and it
 * always writes a new version rather than touching the original.
 */
export function EditorDialog({ asset, open, onOpenChange }: EditorDialogProps) {
    const { r } = useMediaPanel();
    const form = useForm<EditorValues>(emptyValues());
    const [dirtyEnough, setDirtyEnough] = useState(false);

    const { data, setDefaults, reset, clearErrors } = form;

    useEffect(() => {
        setDefaults(emptyValues());
        reset();
        clearErrors();
    }, [asset, clearErrors, reset, setDefaults]);

    useEffect(() => {
        setDirtyEnough(
            data.rotate % 360 !== 0 || data.width !== '' || data.height !== '' || (data.crop_width !== '' && data.crop_height !== ''),
        );
    }, [data]);

    if (!asset) {
        return null;
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!asset) {
            return;
        }

        form.transform((values) => {
            const numeric = (value: string): number | null => (value === '' ? null : Number(value));

            return {
                rotate: values.rotate % 360 === 0 ? null : values.rotate,
                crop_x: numeric(values.crop_x),
                crop_y: numeric(values.crop_y),
                crop_width: numeric(values.crop_width),
                crop_height: numeric(values.crop_height),
                width: numeric(values.width),
                height: numeric(values.height),
                quality: values.quality,
            };
        });

        form.post(r('edit', asset.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <form noValidate onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>Edit {asset.name}</DialogTitle>
                        <DialogDescription>Changes are saved as a new version. The original file is never overwritten.</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-6 md:grid-cols-[1.4fr_1fr]">
                        <div className="flex items-center justify-center overflow-hidden rounded-lg bg-muted p-3">
                            {asset.preview_url ? (
                                <img
                                    src={asset.preview_url}
                                    alt={asset.alt ?? asset.name}
                                    className="max-h-[45svh] w-auto object-contain transition-transform duration-200"
                                    style={{ transform: `rotate(${data.rotate}deg)` }}
                                />
                            ) : (
                                <p className="p-8 text-sm text-muted-foreground">No preview available.</p>
                            )}
                        </div>

                        <div className="space-y-5">
                            <div className="space-y-2">
                                <Label id="rotate-label">Rotate</Label>
                                <div className="flex items-center gap-2" role="group" aria-labelledby="rotate-label">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => form.setData('rotate', data.rotate - 90)}
                                    >
                                        <RotateCcw className="size-4" aria-hidden="true" />
                                        Left
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => form.setData('rotate', data.rotate + 90)}
                                    >
                                        <RotateCw className="size-4" aria-hidden="true" />
                                        Right
                                    </Button>
                                    <span className="text-sm text-muted-foreground tabular-nums">{data.rotate % 360}°</span>
                                </div>
                            </div>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium">Resize</legend>
                                <p className="text-xs text-muted-foreground">Leave both empty to keep the current dimensions.</p>
                                <div className="grid grid-cols-2 gap-2 pt-1">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit-width" className="text-xs">
                                            Width
                                        </Label>
                                        <Input
                                            id="edit-width"
                                            inputMode="numeric"
                                            placeholder={asset.width ? String(asset.width) : 'auto'}
                                            value={data.width}
                                            onChange={(event) => form.setData('width', event.target.value)}
                                            aria-invalid={form.errors.width ? true : undefined}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="edit-height" className="text-xs">
                                            Height
                                        </Label>
                                        <Input
                                            id="edit-height"
                                            inputMode="numeric"
                                            placeholder={asset.height ? String(asset.height) : 'auto'}
                                            value={data.height}
                                            onChange={(event) => form.setData('height', event.target.value)}
                                            aria-invalid={form.errors.height ? true : undefined}
                                        />
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset className="space-y-2">
                                <legend className="text-sm font-medium">Crop</legend>
                                <p className="text-xs text-muted-foreground">In pixels, measured from the top-left corner.</p>
                                <div className="grid grid-cols-2 gap-2 pt-1">
                                    {(
                                        [
                                            ['crop_x', 'X'],
                                            ['crop_y', 'Y'],
                                            ['crop_width', 'Width'],
                                            ['crop_height', 'Height'],
                                        ] as const
                                    ).map(([key, label]) => (
                                        <div key={key} className="space-y-1.5">
                                            <Label htmlFor={`edit-${key}`} className="text-xs">
                                                {label}
                                            </Label>
                                            <Input
                                                id={`edit-${key}`}
                                                inputMode="numeric"
                                                value={data[key] as string}
                                                onChange={(event) => form.setData(key, event.target.value)}
                                                aria-invalid={form.errors[key] ? true : undefined}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </fieldset>

                            <div className="space-y-2">
                                <Label htmlFor="edit-quality">Quality — {data.quality}%</Label>
                                <Slider
                                    id="edit-quality"
                                    min={10}
                                    max={100}
                                    step={5}
                                    value={[data.quality]}
                                    onValueChange={([value]) => form.setData('quality', value ?? 90)}
                                />
                            </div>
                        </div>
                    </div>

                    {!dirtyEnough && (
                        <Alert>
                            <Info className="size-4" aria-hidden="true" />
                            <AlertTitle>Nothing to apply yet</AlertTitle>
                            <AlertDescription>Rotate, resize or crop before saving a new version.</AlertDescription>
                        </Alert>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={form.processing} disabled={!dirtyEnough || form.processing}>
                            Save as new version
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
