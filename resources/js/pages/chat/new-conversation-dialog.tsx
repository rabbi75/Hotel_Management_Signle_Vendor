import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { ChatMemberOption, ConversationType } from '@/types/chat';
import { useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useId, useState } from 'react';

interface NewConversationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    members: ChatMemberOption[];
}

interface ConversationFormValues {
    type: ConversationType;
    name: string;
    description: string;
    participant_ids: number[];
    [key: string]: string | number[] | ConversationType;
}

export function NewConversationDialog({ open, onOpenChange, members }: NewConversationDialogProps) {
    const [filter, setFilter] = useState('');
    const nameId = useId();
    const descriptionId = useId();
    const filterId = useId();

    const form = useForm<ConversationFormValues>({
        type: 'direct',
        name: '',
        description: '',
        participant_ids: [],
    });

    const { data, setData, errors, processing } = form;

    const visible = members.filter((member) => member.name.toLowerCase().includes(filter.trim().toLowerCase()));

    function choose(id: number): void {
        if (data.type === 'direct') {
            setData('participant_ids', [id]);

            return;
        }

        setData(
            'participant_ids',
            data.participant_ids.includes(id) ? data.participant_ids.filter((value) => value !== id) : [...data.participant_ids, id],
        );
    }

    function submit(): void {
        form.post(route('chat.conversations.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setFilter('');
                onOpenChange(false);
            },
        });
    }

    function switchType(type: string): void {
        // Direct conversations take exactly one other person, so a multi
        // selection made on the group tab cannot carry over.
        setData((current) => ({
            ...current,
            type: type as ConversationType,
            participant_ids: type === 'direct' ? current.participant_ids.slice(0, 1) : current.participant_ids,
        }));
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Start a conversation</DialogTitle>
                    <DialogDescription>Message one person directly, or set up a room for a group.</DialogDescription>
                </DialogHeader>

                <Tabs value={data.type} onValueChange={switchType}>
                    <TabsList className="w-full">
                        <TabsTrigger value="direct" className="flex-1">
                            Direct
                        </TabsTrigger>
                        <TabsTrigger value="group" className="flex-1">
                            Group
                        </TabsTrigger>
                        <TabsTrigger value="channel" className="flex-1">
                            Channel
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                {data.type !== 'direct' && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor={nameId}>
                                Name
                                <span className="text-destructive" aria-hidden="true">
                                    *
                                </span>
                            </Label>
                            <Input
                                id={nameId}
                                value={data.name}
                                aria-invalid={errors.name ? true : undefined}
                                onChange={(event) => setData('name', event.target.value)}
                            />
                            {errors.name && (
                                <p className="flex items-start gap-1.5 text-sm text-destructive">
                                    <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={descriptionId}>Description</Label>
                            <Textarea
                                id={descriptionId}
                                rows={2}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                            />
                        </div>
                    </div>
                )}

                <div className="space-y-2">
                    <Label htmlFor={filterId}>People</Label>
                    <Input
                        id={filterId}
                        type="search"
                        value={filter}
                        placeholder="Filter by name"
                        onChange={(event) => setFilter(event.target.value)}
                    />

                    {visible.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">No workspace members match that.</p>
                    ) : (
                        <ul className="max-h-56 overflow-y-auto rounded-md border border-border" aria-label="Workspace members">
                            {visible.map((member) => {
                                const selected = data.participant_ids.includes(member.id);

                                return (
                                    <li key={member.id}>
                                        <label className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-accent">
                                            <Checkbox
                                                checked={selected}
                                                onCheckedChange={() => choose(member.id)}
                                                aria-label={`Select ${member.name}`}
                                            />
                                            <Avatar size="xs">
                                                {member.avatar && <AvatarImage src={member.avatar} alt="" />}
                                                <AvatarFallback>{member.initials}</AvatarFallback>
                                            </Avatar>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm text-foreground">{member.name}</span>
                                                {member.job_title && (
                                                    <span className="block truncate text-xs text-muted-foreground">{member.job_title}</span>
                                                )}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {errors.participant_ids && (
                        <p className="flex items-start gap-1.5 text-sm text-destructive">
                            <CircleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            {errors.participant_ids}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={submit} disabled={processing || data.participant_ids.length === 0}>
                        Start conversation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
