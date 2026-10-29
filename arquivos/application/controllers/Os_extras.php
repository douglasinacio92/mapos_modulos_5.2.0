<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Impressões extras da OS (Daitech): 3 etiquetas 35x21mm (bobina de
 * 105x21mm) e o cupom 80mm com a via do cliente e a da loja, os termos, a
 * assinatura digital e os QR codes (acompanhar a OS e WhatsApp do cliente). Só leitura, com a permissão de ver OS (vOs).
 */
class Os_extras extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('os_model');
        $this->load->model('mapos_model');
    }

    public function etiquetas()
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            return $this->negarAcesso();
        }

        $os = $this->osDaUrl();
        $this->load->view('os_extras/imprimirEtiquetas', [
            'os' => $os,
            'emitente' => $this->mapos_model->getEmitente(),
        ]);
    }

    public function cupom()
    {
        if (! $this->hasAnyPermission(['vOs'])) {
            return $this->negarAcesso();
        }

        $os = $this->osDaUrl();
        $idOs = (int) $os->idOs;
        $produtos = $this->os_model->getProdutos($idOs);
        $servicos = $this->os_model->getServicos($idOs);

        $totalProdutos = array_sum(array_map(static fn ($p) => (float) $p->subTotal, $produtos));
        $totalServicos = array_sum(array_map(static fn ($s) => (float) ($s->subTotal ?? (($s->preco ?: ($s->precoVenda ?? 0)) * ($s->quantidade ?: 1))), $servicos));
        $bruto = round($totalProdutos + $totalServicos, 2);
        $total = (float) $os->valor_desconto > 0 && (float) $os->valor_desconto < $bruto ? (float) $os->valor_desconto : $bruto;

        // Sempre duas vias: a do cliente (com o QR para acompanhar a OS) e a da
        // loja (com a senha do aparelho, a assinatura e o QR do WhatsApp do cliente).
        $vias = ['VIA DO CLIENTE', 'VIA DA LOJA'];
        $linkStatus = site_url('acompanhar/qr/' . $idOs . '/' . daitechTokenAcompanhar($idOs, daitechChaveAcompanhar()));
        $telefone = daitechTelefone($os->celular_cliente ?: $os->telefone_cliente);

        $this->load->view('os_extras/imprimirCupom', [
            'os' => $os,
            'produtos' => $produtos,
            'servicos' => $servicos,
            'bruto' => $bruto,
            'total' => $total,
            'vias' => $vias,
            'emitente' => $this->mapos_model->getEmitente(),
            'termos' => trim((string) ($this->data['configuration']['dt_termos_os'] ?? '')),
            'qrStatus' => daitechQrCode($linkStatus),
            'qrWhatsapp' => $telefone !== null ? daitechQrCode('https://wa.me/' . $telefone) : null,
        ]);
    }

    private function osDaUrl(): object
    {
        $os = is_numeric($this->uri->segment(3)) ? $this->os_model->getById((int) $this->uri->segment(3)) : null;
        if (! $os) {
            $this->session->set_flashdata('error', 'OS não encontrada.');
            redirect('os');
        }

        return $os;
    }
}
