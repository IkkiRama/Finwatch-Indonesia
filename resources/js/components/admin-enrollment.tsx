import { useState, type FormEvent } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Check, Copy, Eye, EyeOff, ShieldCheck, Smartphone } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api, csrf, errorMessage } from '../lib/api';
import { Brand, Footer } from './shared';
import { Button } from './ui/button';

export default function AdminEnrollment() {
  const client=useQueryClient();
  const [pending,setPending]=useState<{secret:string;otpauth_uri?:string}|null>(null);
  const [code,setCode]=useState(''); const [busy,setBusy]=useState(false); const [error,setError]=useState(''); const [show,setShow]=useState(false); const [copied,setCopied]=useState(false);
  async function begin() {
    setBusy(true); setError('');
    try {await csrf(); setPending((await api.post<{secret:string;otpauth_uri?:string}>('/admin/mfa/enrollment')).data);}
    catch(failure){setError(errorMessage(failure));} finally{setBusy(false);}
  }
  async function confirm(event:FormEvent){event.preventDefault();setBusy(true);setError('');try{await csrf();await api.post('/admin/mfa/enrollment/confirm',{code});setPending(null);setCode('');await client.invalidateQueries({queryKey:['me']});}catch(failure){setError(errorMessage(failure));setCode('');}finally{setBusy(false);}}
  return <div className="access-screen"><Brand /><section className="access-card enrollment-card"><div className="enrollment-icon"><ShieldCheck size={28}/></div><p className="eyebrow">SELAMAT DATANG, ADMIN</p><h1>Amankan akunmu sekali saja.</h1><p>Selain login Google, workspace memakai kode dari aplikasi autentikator. Supaya akses pengelolaan tetap milikmu.</p>{!pending ? <><ol className="setup-steps"><li><span>1</span><div><strong>Siapkan aplikasi autentikator</strong><p>Gunakan Google Authenticator, Microsoft Authenticator, atau aplikasi TOTP pilihanmu.</p></div></li><li><span>2</span><div><strong>Hubungkan akun FINWATCH</strong><p>Salin kunci penyiapan ke aplikasi, lalu konfirmasi kode enam digitnya.</p></div></li></ol><Button disabled={busy} onClick={()=>void begin()}>{busy?'Menyiapkan…':'Mulai penyiapan'}<Smartphone size={17}/></Button></> : <form onSubmit={confirm}><ol className="setup-steps"><li><span>1</span><div><strong>Tambahkan akun secara manual</strong><p>Di aplikasi autentikator pilih “Masukkan kunci penyiapan”. Nama akun: FINWATCH. Jenis kunci: berbasis waktu.</p></div></li></ol><label htmlFor="enrollment-secret">Kunci penyiapan privat</label><div className="setup-secret"><input id="enrollment-secret" type={show?'text':'password'} readOnly value={pending.secret} autoComplete="off" spellCheck={false}/><button type="button" aria-label={show?'Sembunyikan kunci':'Tampilkan kunci'} onClick={()=>setShow(!show)}>{show?<EyeOff size={17}/>:<Eye size={17}/>}</button><button type="button" aria-label="Salin kunci" onClick={async()=>{try{await navigator.clipboard.writeText(pending.secret);setCopied(true);}catch{setShow(true);setError('Salin kunci yang ditampilkan secara manual.');}}}>{copied?<Check size={17}/>:<Copy size={17}/>}</button></div><p className="setup-private">Simpan kunci di tempat privat. Jangan bagikan melalui chat atau tangkapan layar.</p><label htmlFor="enrollment-code">Kode enam digit dari aplikasi</label><input id="enrollment-code" className="totp-input" value={code} onChange={event=>setCode(event.target.value.replace(/\D/g,'').slice(0,6))} inputMode="numeric" autoComplete="one-time-code" maxLength={6} required/><Button disabled={busy||code.length!==6}>{busy?'Memeriksa…':'Konfirmasi & buka workspace'}</Button></form>}{error&&<div className="inline-error" role="alert"><p>{error}</p><a className="text-link" href="/auth/google/redirect?return_to=%2Fadmin">Masuk ulang dengan Google</a></div>}<Link className="subtle-link" to="/">Kembali ke halaman publik</Link></section><Footer /></div>;
}
