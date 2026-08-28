import { Head, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    can_access_finance: boolean;
    can_access_crm: boolean;
    is_active: boolean;
};

type FlagField =
    | 'is_admin'
    | 'can_access_finance'
    | 'can_access_crm'
    | 'is_active';

const columns: { field: FlagField; label: string; selfLocked: boolean }[] = [
    { field: 'is_admin', label: 'Admin', selfLocked: true },
    { field: 'can_access_finance', label: 'Finance', selfLocked: false },
    { field: 'can_access_crm', label: 'Business', selfLocked: false },
    { field: 'is_active', label: 'Active', selfLocked: true },
];

export default function UsersIndex({ users }: { users: ManagedUser[] }) {
    const { auth } = usePage().props;
    const currentUserId = auth.user.id;

    function updateFlag(user: ManagedUser, field: FlagField, value: boolean) {
        router.patch(
            `/settings/users/${user.id}`,
            {
                is_admin: user.is_admin,
                can_access_finance: user.can_access_finance,
                can_access_crm: user.can_access_crm,
                is_active: user.is_active,
                [field]: value,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Users" />
            <h1 className="sr-only">Users</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Users"
                    description="Grant or revoke Finance and Business access for company users."
                />

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
    breadcrumbs: [{ title: 'Users', href: '/settings/users' }],
};
