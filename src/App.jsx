import { useState } from 'react';
import ChatWidget from './components/ChatWidget';

const OPEN_KEY = 'wradmin_widget_open';

function readOpen() {
    try {
        const current = localStorage.getItem(OPEN_KEY);
        return (current ?? localStorage.getItem('waa_widget_open')) === '1';
    } catch { return false; }
}

export default function App() {
    const [isOpen, setIsOpen] = useState(readOpen);

    function handleToggle() {
        setIsOpen(o => {
            const next = !o;
            try { localStorage.setItem(OPEN_KEY, next ? '1' : '0'); } catch {}
            return next;
        });
    }

    return (
        <ChatWidget
            isOpen={isOpen}
            onToggle={handleToggle}
        />
    );
}
