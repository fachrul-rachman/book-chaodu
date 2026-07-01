import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type LoginProps = {
    title: string;
};

export default function Login({ title }: LoginProps) {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/masuk');
    };

    return (
        <>
            <Head title={title} />

            <main className="flex min-h-screen items-center justify-center px-4 py-10">
                <div className="w-full max-w-md rounded-[28px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                    <p className="text-sm font-medium tracking-[0.18em] text-[var(--color-brand)] uppercase">
                        Chao Du Booking
                    </p>
                    <h1 className="mt-4 text-3xl font-semibold text-[var(--color-ink)]">
                        {title}
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-slate-700">
                        Masukkan email dan kata sandi Anda.
                    </p>

                    <form className="mt-8 space-y-4" onSubmit={submit}>
                        <label className="block">
                            <span className="mb-2 block text-sm font-medium text-slate-800">
                                Email
                            </span>
                            <input
                                type="email"
                                autoComplete="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base ring-0 outline-none"
                            />
                            {form.errors.email ? (
                                <p className="mt-2 text-sm text-red-700">
                                    {form.errors.email}
                                </p>
                            ) : null}
                        </label>

                        <label className="block">
                            <span className="mb-2 block text-sm font-medium text-slate-800">
                                Kata sandi
                            </span>
                            <input
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base ring-0 outline-none"
                            />
                            {form.errors.password ? (
                                <p className="mt-2 text-sm text-red-700">
                                    {form.errors.password}
                                </p>
                            ) : null}
                        </label>

                        <label className="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={form.data.remember}
                                onChange={(event) =>
                                    form.setData(
                                        'remember',
                                        event.target.checked,
                                    )
                                }
                            />
                            Tetap masuk di perangkat ini
                        </label>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing ? 'Memproses...' : 'Masuk'}
                        </button>
                    </form>
                </div>
            </main>
        </>
    );
}
