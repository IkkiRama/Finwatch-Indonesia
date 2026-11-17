import { useCallback, useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { MessageSquare, X } from 'lucide-react';
import { Button } from './button';
import '../../../css/action-dialog.css';

type DialogOptions = { title: string; description: string; confirmLabel?: string; danger?: boolean; input?: { label: string; initialValue?: string; minLength?: number; maxLength?: number; placeholder?: string; multiline?: boolean } };
type PendingDialog = DialogOptions & { resolve: (value: string | null) => void };

export function useActionDialog(): { dialog: ReactNode; confirm: (options: DialogOptions) => Promise<boolean>; prompt: (options: DialogOptions & { input: NonNullable<DialogOptions['input']> }) => Promise<string | null> } {
  const [pending, setPending] = useState<PendingDialog | null>(null);
  const resolver = useRef<PendingDialog['resolve'] | null>(null);
  const open = useCallback((options: DialogOptions) => new Promise<string | null>(resolve => {
    resolver.current?.(null);
    resolver.current = resolve;
    setPending({ ...options, resolve });
  }), []);
  const finish = useCallback((value: string | null) => {
    resolver.current?.(value);
    resolver.current = null;
    setPending(null);
  }, []);
  useEffect(() => () => { resolver.current?.(null); }, []);
  return {
    dialog: pending ? createPortal(<ActionDialog key={pending.title} options={pending} onFinish={finish} />, document.body) : null,
    confirm: useCallback(async (options: DialogOptions) => (await open(options)) !== null, [open]),
    prompt: open,
  };
}

function ActionDialog({ options, onFinish }: { options: DialogOptions; onFinish: (value: string | null) => void }) {
  const element = useRef<HTMLDialogElement>(null);
  const [value, setValue] = useState(options.input?.initialValue || '');
  const titleId = useId(); const descriptionId = useId(); const inputId = useId();
  useEffect(() => {
    const dialog = element.current;
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog?.showModal();
    return () => { dialog?.close(); previousFocus?.focus(); };
  }, []);
  return <dialog ref={element} className="action-dialog" aria-labelledby={titleId} aria-describedby={descriptionId} onCancel={event => { event.preventDefault(); onFinish(null); }}>
    <div className="action-dialog-top"><span className="action-dialog-symbol"><MessageSquare size={22} /></span><Button type="button" size="icon" variant="ghost" aria-label="Tutup dialog" onClick={() => onFinish(null)}><X size={19} /></Button></div>
    <h2 id={titleId}>{options.title}</h2><p id={descriptionId}>{options.description}</p>
    <form onSubmit={event => { event.preventDefault(); onFinish(options.input ? value.trim() : 'confirmed'); }}>
      {options.input && <label htmlFor={inputId}>{options.input.label}{options.input.multiline ? <textarea id={inputId} value={value} onChange={event => setValue(event.target.value)} minLength={options.input.minLength || 1} maxLength={options.input.maxLength || 500} placeholder={options.input.placeholder} rows={4} required autoFocus /> : <input id={inputId} value={value} onChange={event => setValue(event.target.value)} minLength={options.input.minLength || 1} maxLength={options.input.maxLength || 500} placeholder={options.input.placeholder} required autoFocus />}</label>}
      <div className="action-dialog-actions"><Button type="button" variant="outline" onClick={() => onFinish(null)} autoFocus={!options.input}>Batal</Button><Button type="submit" className={options.danger ? 'action-dialog-danger' : undefined} disabled={Boolean(options.input && value.trim().length < (options.input.minLength || 1))}>{options.confirmLabel || 'Lanjutkan'}</Button></div>
    </form>
  </dialog>;
}
