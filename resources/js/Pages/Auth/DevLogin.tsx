import Avatar from '@/Components/ui/Avatar';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** Demo login for testing: locked behind a passcode on any hosted server; the account list arrives only after it. */
export default function DevLogin({ locked, accounts }: { locked: boolean; accounts: { email: string; name: string; detail: string }[] }) {
    const unlock = useForm({ passcode: '' });
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        unlock.post('/dev/login-as/unlock', { onFinish: () => unlock.reset() });
    };
    return (
        <GuestLayout>
            <Head title="Demo sign-in" />
            <Card title="Demo sign-in">
                {locked ? (
                    <form className="grid gap-3" onSubmit={submit}>
                        <p className="text-sm text-ink-muted">Testing access. Enter the demo passcode to continue.</p>
                        <Input value={unlock.data.passcode} onChange={(v) => unlock.setData('passcode', v)} type="password" label="Passcode" autoComplete="off" error={unlock.errors.passcode} />
                        <Button type="submit" loading={unlock.processing}>
                            Unlock
                        </Button>
                    </form>
                ) : (
                    <ul className="grid max-h-[70dvh] gap-1 overflow-y-auto">
                        {accounts.map((account) => (
                            <li key={account.email}>
                                <button type="button" className="flex w-full items-center gap-3 rounded-control p-2 text-left hover:bg-surface-muted" onClick={() => router.post('/dev/login-as', { email: account.email })}>
                                    <Avatar name={account.name} size="sm" />
                                    <span className="grid">
                                        <span className="text-sm font-medium">{account.name}</span>
                                        <span className="text-xs text-ink-muted">{account.detail}</span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </GuestLayout>
    );
}
