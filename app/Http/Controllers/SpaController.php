<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SpaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $titles = ['/' => 'Ringkasan', 'perbankan' => 'Perbankan', 'pemantauan-risiko' => 'Pemantauan Risiko', 'peringatan-dini' => 'Peringatan Dini', 'simulasi-stres' => 'Simulasi Stres', 'riset' => 'Riset & Kebijakan', 'inklusi-dan-konsumen' => 'Inklusi & Konsumen', 'artikel' => 'Edukasi Keuangan', 'jelajah-data' => 'Jelajah Data', 'sumber-data' => 'Sumber Data', 'tentang' => 'Tentang', 'masuk' => 'Masuk'];

        return view('app', ['title' => ($titles[$request->path()] ?? 'Workspace Admin').' | FINWATCH Indonesia', 'noindex' => $request->is('admin*', 'masuk')]);
    }
}
