import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_active: boolean;
};

type FlagField = 'is_admin' | 'is_active';

const columns: { field: FlagField; label: string; selfLocked: boolean }[] = [
    { field: 'is_admin', label: 'Admin', selfLocked: true },
    { field: 'is_active', label: 'Active', selfLocked: true },
];

function InviteDialog({
    onOpenChange,
}: {
    onOpenChange: (o: boolean) => void;
}) {
    const form = useForm({
        name: '',
        email: '',
        is_admin: false as boolean,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/configuration/users', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <DialogContent>
            <form onSubmit={submit}>
                <DialogHeader>
                    <DialogTitle>Invite user</DialogTitle>
                    <DialogDescription>
                        Creates the account and gives you a link to send them so
                        they can set their password.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="is_admin"
                            checked={form.data.is_admin === true}
                            onCheckedChange={(v) =>
                                form.setData('is_admin', v === true)
                            }
                        />
                        <Label htmlFor="is_admin">Administrator</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Invite
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
}

export default function UsersIndex({ users }: { users: ManagedUser[] }) {
    const page = usePage();
    const currentUserId = page.props.auth.user.id;
    const inviteLink = (page.props as { flash?: { invite_link?: string } })
        .flash?.invite_link;
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    function updateFlag(user: ManagedUser, field: FlagField, value: boolean) {
        router.patch(
            `/configuration/users/${user.id}`,
            {
                is_admin: user.is_admin,
                is_active: user.is_active,
                [field]: value,
            },
            { preserveScroll: true },
        );
    }

    function copyLink() {
        if (!inviteLink) return;
        navigator.clipboard
            .writeText(inviteLink)
            .then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => {});
    }

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Users</h1>
                        <p className="text-muted-foreground">
                            Invite people to the app and manage admin access.
                        </p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <Button onClick={() => setOpen(true)}>
                            <Plus className="size-4" />
                            Invite user
                        </Button>
                        <InviteDialog onOpenChange={setOpen} />
                    </Dialog>
                </div>

                {inviteLink && (
                    <div className="border-primary/40 bg-primary/5 grid gap-2 rounded-lg border p-4">
                        <div className="text-sm font-medium">
                            Invite link — send this to the new user (valid 3
                            days):
                        </div>
                        <div className="flex items-center gap-2">
                            <Input
                                readOnly
                                value={inviteLink}
                                className="font-mono text-xs"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={copyLink}
                                aria-label="Copy invite link"
                            >
                                {copied ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                            </Button>
                        </div>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">User</th>
                                {columns.map((col) => (
                                    <th
                                        key={col.field}
                                        className="p-3 text-center font-medium"
                                    >
                                        {col.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => {
                                const isSelf = user.id === currentUserId;

                                return (
                                    <tr key={user.id} className="border-t">
                                        <td className="p-3">
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </td>
                                        {columns.map((col) => (
                                            <td key={col.field} className="p-3">
                                                <div className="flex justify-center">
                                                    <Checkbox
                                                        checked={
                                                            user[col.field]
                                                        }
                                                        disabled={
                                                            col.selfLocked &&
                                                            isSelf
                                                        }
                                                        onCheckedChange={(v) =>
                                                            updateFlag(
                                                                user,
                                                                col.field,
                                                                v === true,
                                                            )
                                                        }
                                                        aria-label={`Toggle ${col.label} for ${user.name}`}
                                                    />
                                                </div>
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: '/configuration/users' }],
};
