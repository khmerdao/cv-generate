import { useState } from 'react';

type Props = { name: string; greeting: string };

export default function HelloIsland({ name, greeting }: Props) {
    const [clicks, setClicks] = useState(0);
    return (
        <div className="rounded-xl2 border border-surface-line bg-surface p-4 shadow-soft">
            <p role="status" className="font-medium text-ink">
                {greeting}, {name}
            </p>
            <button
                type="button"
                className="mt-2 text-sm text-accent"
                onClick={() => setClicks((c) => c + 1)}
            >
                React OK ({clicks})
            </button>
        </div>
    );
}
