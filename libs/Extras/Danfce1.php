<?php

namespace NFePHP\Extras;

/**
 * Classe para a impressÃ£o em PDF do Docuimento Auxiliar de NFe Consumidor
 *
 * @category  NFePHP
 * @package   NFePHP\NFe\ConvertNFe
 * @copyright Copyright (c) 2008-2015
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @author    Roberto Spadim <roberto at spadim dot com dot br>
 * @link      http://github.com/nfephp-org/nfephp for the canonical source repository
 *
 * CONTRIBUIDORES (por ordem alfabetica):
 *            Roberto L. Machado <linux dot rlm at gmail dot com>
 *            Mario Almeida <mario at grupopmz dot com dot br>
 */

//ajuste do tempo limite de resposta do processo
set_time_limit(100);

//classes utilizadas
use NFePHP\Extras\CommonNFePHP;
use NFePHP\Extras\DocumentoNFePHP;
use NFePHP\Extras\DomDocumentNFePHP;
use Endroid\QrCode\QrCode;

/**
 * Classe Danfce1
 * Objetivo - impressÃ£o de NFC-e em uma unica pagina (bobina)
 */
class Danfce1 extends CommonNFePHP implements DocumentoNFePHP
{
    //publicas
    public $papel;
    
    //privadas
    protected $xml; // string XML NFe
    protected $logomarca=''; // path para logomarca em jpg
    protected $formatoChave="#### #### #### #### #### #### #### #### #### #### ####";
    protected $debugMode=0; //ativa ou desativa o modo de debug
    protected $tpImp; //ambiente
    protected $fontePadrao='Times';
    protected $nfeProc;
    protected $nfe;
    protected $infNFe;
    protected $ide;
    protected $enderDest;
    protected $ICMSTot;
    protected $imposto;
    protected $emit;
    protected $enderEmit;
    protected $qrCode;
    protected $det;
    protected $pag;
    protected $dest;
    protected $imgQRCode;
    protected $urlQR = '';
    protected $pdf;
    protected $margemInterna = 2;
    protected $hMaxLinha = 9;
    protected $hBoxLinha = 6;
    protected $hLinha = 3;
    
    /**
     * __contruct
     *
     * @param string $docXML
     * @param string $sPathLogo
     * @param string $mododebug
     * @param string $idToken
     * @param string $Token
     */
    public function __construct(
        $docXML = '',
        $sPathLogo = '',
        $mododebug = 0,
        // habilita os erros do sistema
        $idToken = '',
        $emitToken = '',
        $urlQR = ''
    ) {
    
        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($this->debugMode) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } else {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        $this->xml = $docXML;
        $this->logomarca = $sPathLogo;
        if (empty($fonteDANFE)) {
            $this->fontePadrao = 'Times';
        } else {
            $this->fontePadrao = $fonteDANFE;
        }
        if (!empty($this->xml)) {
            $this->dom = new DomDocumentNFePHP();
            $this->dom->loadXML($this->xml);
            $this->nfeProc    = $this->dom->getElementsByTagName("nfeProc")->item(0);
            $this->nfe        = $this->dom->getElementsByTagName("NFe")->item(0);
            $this->infNFe     = $this->dom->getElementsByTagName("infNFe")->item(0);
            $this->ide        = $this->dom->getElementsByTagName("ide")->item(0);
            $this->emit       = $this->dom->getElementsByTagName("emit")->item(0);
            $this->enderEmit  = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->det        = $this->dom->getElementsByTagName("det");
            $this->dest       = $this->dom->getElementsByTagName("dest")->item(0);
            $this->pag        = $this->dom->getElementsByTagName("pag");
            $this->imposto    = $this->dom->getElementsByTagName("imposto")->item(0);
            $this->ICMSTot    = $this->dom->getElementsByTagName("ICMSTot")->item(0);
            $this->tpImp      = $this->ide->getElementsByTagName("tpImp")->item(0)->nodeValue;
        }
        $this->qrCode = $this->dom->getElementsByTagName('qrCode')->item(0)->nodeValue;
        if ($this->pSimpleGetValue($this->ide, "mod") != '65') {
            throw new nfephpException("O xml do DANFE deve ser uma NFC-e modelo 65");
        }
    }

    public function getPapel()
    {
        return $this->papel;
    }
    
    public function setPapel($aPap)
    {
        $this->papel = $aPap;
    }
    
    public function simpleConsistencyCheck()
    {
        if (1 == 2 || $this->xml == null || $this->infNFe == null || $this->ide == null) {
            return false;
        }
        return true;
    }

    public function montaDANFE(
        $orientacao = 'P',
        $papel = '',
        $logoAlign = 'C',
        $situacaoExterna = NFEPHP_SITUACAO_EXTERNA_NONE,
        $classPdf = false,
        $depecNumReg = ''
    ) {

        $qtdItens = $this->det->length;
        $qtdPgto = $this->pag->length;
        $hMaxLinha = $this->hMaxLinha;
        $hBoxLinha = $this->hBoxLinha;
        $hLinha = $this->hLinha;
        $tamPapelVert = 160 + (($qtdItens-1)*$hMaxLinha) + ($qtdPgto*$hLinha);
        
        //se a orientaÃ§Ã£o estiver em branco utilizar o padrÃ£o estabelecido na NF
        if ($orientacao == '') {
            $orientacao = 'P';
        }
        $this->orientacao = $orientacao;
        $this->papel = array(80,$tamPapelVert);
        $this->logoAlign = $logoAlign;
        $this->situacao_externa = $situacaoExterna;
        $this->numero_registro_dpec = $depecNumReg;
        //instancia a classe pdf
        if ($classPdf) {
            $this->pdf = $classPdf;
        } else {
            $this->pdf = new PdfNFePHP($this->orientacao, 'mm', $this->papel);
        }

        //margens do PDF, em milÃ­metros. Obs.: a margem direita Ã© sempre igual Ã 
        //margem esquerda. A margem inferior *nÃ£o* existe na FPDF, Ã© definida aqui
        //apenas para controle se necessÃ¡rio ser maior do que a margem superior
        $margSup = 2;
        $margEsq = 2;
        $margInf = 2;
        // posiÃ§Ã£o inicial do conteÃºdo, a partir do canto superior esquerdo da pÃ¡gina
        $xInic = $margEsq;
        $yInic = $margSup;
        $maxW = 80;
        $maxH = $tamPapelVert;
        //total inicial de paginas
        $totPag = 1;
        //largura imprimivel em mm: largura da folha menos as margens esq/direita
        $this->wPrint = $maxW-($margEsq*2);
        //comprimento (altura) imprimivel em mm: altura da folha menos as margens
        //superior e inferior
        $this->hPrint = $maxH-$margSup-$margInf;
        // estabelece contagem de paginas
        $this->pdf->AliasNbPages();
        $this->pdf->SetMargins($margEsq, $margSup); // fixa as margens
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Open(); // inicia o documento
        $this->pdf->AddPage($this->orientacao, $this->papel); // adiciona a primeira pÃ¡gina
        $this->pdf->SetLineWidth(0.1); // define a largura da linha
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pTextBox(0, 0, $maxW, $maxH); // POR QUE PRECISO DESA LINHA?

        $hcabecalho = 27;//para cabeÃ§alho (dados emitente mais logomarca)  (FIXO)
        $hcabecalhoSecundario = 10;//para cabeÃ§alho secundÃ¡rio (cabeÃ§alho sefaz) (FIXO)
        $hprodutos = $hLinha + ($qtdItens*$hMaxLinha) ;//box poduto
        $hTotal = 12; //box total (FIXO)
        $hpagamentos = $hLinha + ($qtdPgto*$hLinha);//para pagamentos
        $hmsgfiscal = 21;// para imposto (FIXO)
        if (!isset($this->dest)) {
            $hcliente = 6;// para cliente (FIXO)
        } else {
            $hcliente = 12;
        }// para cliente (FIXO)};
        $hQRCode = 50;// para qrcode (FIXO)
        $hCabecItens = 4;//cabeÃ§alho dos itens
        
        $hUsado = $hCabecItens;
        $w2 = round($this->wPrint*0.31, 0);
        $totPag = 1;
        $pag = 1;
        $x = $xInic;
        
        //COLOCA CABEÃ‡ALHO
        $y = $yInic;
        $y = $this->pCabecalhoDANFE($x, $y, $hcabecalho, $pag, $totPag);
       
        //COLOCA CABEÃ‡ALHO SECUNDÃ�RIO
        $y = $hcabecalho;
        $y = $this->pCabecalhoSecundarioDANFE($x, $y, $hcabecalhoSecundario);
        
        //COLOCA PRODUTOS
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario;
        $y = $this->pProdutosDANFE($x, $y, $hprodutos);
       
        //COLOCA TOTAL
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario + $hprodutos;
        $y = $this->pTotalDANFE($x, $y, $hTotal);
              
        //COLOCA PAGAMENTOS
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario + $hprodutos + $hTotal;
        $y = $this->pPagamentosDANFE($x, $y, $hpagamentos);
       
        //COLOCA MENSAGEM FISCAL
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario + $hprodutos + $hTotal+ $hpagamentos;
        $y = $this->pFiscalDANFE($x, $y, $hmsgfiscal);
        
        //COLOCA CONSUMIDOR
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario + $hprodutos + $hTotal + $hpagamentos + $hmsgfiscal;
        $y = $this->pConsumidorDANFE($x, $y, $hcliente);
        
        //COLOCA QRCODE
        $y = $xInic + $hcabecalho + $hcabecalhoSecundario + $hprodutos
            + $hTotal + $hpagamentos + $hmsgfiscal + $hcliente;
        $y = $this->pQRDANFE($x, $y, $hQRCode);
       
        //retorna o ID na NFe
        if ($classPdf!==false) {
            $aR = array(
             'id'=>str_replace('NFe', '', $this->infNFe->getAttribute("Id")),
             'classe_PDF'=>$this->pdf);
            return $aR;
        } else {
            return str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        }
    }//fim da funÃ§Ã£o montaDANFE
    
    protected function pCabecalhoDANFE($x = 0, $y = 0, $h = 0, $pag = '1', $totPag = '1')
    {

        //RECEBE VALORES
        $emitRazao  = $this->pSimpleGetValue($this->emit, "xNome");
        $emitCnpj   = $this->pSimpleGetValue($this->emit, "CNPJ");
        $emitCnpj   = $this->pFormat($emitCnpj, "##.###.###/####-##");
        $emitIE     = $this->pSimpleGetValue($this->emit, "IE");
        $emitIM     = $this->pSimpleGetValue($this->emit, "IM");
        $emitFone = $this->pSimpleGetValue($this->enderEmit, "fone");
        $foneLen = strlen($emitFone);
        if ($foneLen>0) {
            $ddd = substr($emitFone, 0, 2);
            $fone1 = substr($emitFone, -8);
            if ($foneLen == 11) {
                $digito9 = substr($emitFone, 2, 1);
            }
            $emitFone = ' - ('.$ddd.') '.$digito9. ' ' . substr($fone1, 0, 4) . '-' . substr($fone1, -4);
        } else {
            $emitFone = '';
        }
        $emitLgr = $this->pSimpleGetValue($this->enderEmit, "xLgr");
        $emitNro = $this->pSimpleGetValue($this->enderEmit, "nro");
        $emitCpl = $this->pSimpleGetValue($this->enderEmit, "xCpl", "");
        $emitBairro = $this->pSimpleGetValue($this->enderEmit, "xBairro");
        $emitCEP = $this->pFormat($this->pSimpleGetValue($this->enderEmit, "CEP"), "#####-###");
        $emitMun = $this->pSimpleGetValue($this->enderEmit, "xMun");
        $emitUF = $this->pSimpleGetValue($this->enderEmit, "UF");
        
        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $h = $h-($margemInterna);
        
        //COLOCA LOGOMARCA
        if (is_file($this->logomarca)) {
            $xImg = $margemInterna;
            $yImg = $margemInterna + 1;
            $this->pdf->Image($this->logomarca, $xImg, $yImg, 30, 22.5);
            $xRs = ($maxW*0.4) + $margemInterna;
            $wRs = ($maxW*0.6);
            $alignEmit = 'L';
        } else {
            $xRs = $margemInterna;
            $wRs = ($maxW*1);
            $alignEmit = 'C';
        }
        //COLOCA RAZÃƒO SOCIAL
        $texto = $emitRazao;
        $texto = $texto . "\nCNPJ:" . $emitCnpj;
        $texto = $texto . "\nIE:" . $emitIE;
        if (!empty($emitIM)) {
            $texto = $texto . " - IM:" . $emitIM;
        }
        $texto = $texto . "\n" . $emitLgr . "," . $emitNro . " " . $emitCpl . "," . $emitBairro
                . ". CEP:" . $emitCEP . ". " . $emitMun . "-" . $emitUF . $emitFone;
        $this->pTextBox($xRs, $y, $wRs, $h, $texto, $aFont, 'C', $alignEmit, 0, '', false);

    }
    
    protected function pCabecalhoSecundarioDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $w = ($maxW*1);
        //COLOCA MENSAGEM 1
        $hBox1 = 7;
        $texto = "DANFE NFC-e\nDocumento Auxiliar da Nota Fiscal de Consumidor EletrÃ´nica";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $hBox1, $texto, $aFont, 'C', 'C', 0, '', false);
        //COLOCA MENSAGEM 2
        $hBox2 = 4;
        $yBox2 = $y + $hBox1;
        $texto = "\nNFC-e nÃ£o permite aproveitamento de crÃ©dito de ICMS";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $this->pTextBox($x, $yBox2, $w, $hBox2, $texto, $aFont, 'C', 'C', 0, '', false);
        
    }
    
    protected function pProdutosDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $qtdItens = $this->det->length;
        $w = ($maxW*1);
        
        //COLOCA CABEÃ‡ALHO PRODUTOS
        $hLinha = $this->hLinha;
        $aFontCabProdutos = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        
        //COLOCA CÃ“DIGO
        $wBoxCod = $w*0.17;
        $texto = "CÃ“DIGO";
        $this->pTextBox($x, $y, $wBoxCod, $hLinha, $texto, $aFontCabProdutos, 'T', 'L', 0, '', false);
        
        //COLOCA DESCRIÃ‡ÃƒO
        $wBoxDescricao = $w*0.43;
        $xBoxDescricao = $wBoxCod + $x;
        $texto = "DESCRICÃƒO";
        $this->pTextBox(
            $xBoxDescricao,
            $y,
            $wBoxDescricao,
            $hLinha,
            $texto,
            $aFontCabProdutos,
            'T',
            'L',
            0,
            '',
            false
        );
        
        //COLOCA QUANTIDADE
        $wBoxQt = $w*0.08;
        $xBoxQt = $wBoxDescricao + $xBoxDescricao;
        $texto = "QT";
        $this->pTextBox($xBoxQt, $y, $wBoxQt, $hLinha, $texto, $aFontCabProdutos, 'T', 'L', 0, '', false);
        
        //COLOCA UNIDADE
        $wBoxUn = $w*0.06;
        $xBoxUn = $wBoxQt + $xBoxQt;
        $texto = "UN";
        $this->pTextBox($xBoxUn, $y, $wBoxUn, $hLinha, $texto, $aFontCabProdutos, 'T', 'L', 0, '', false);
        
        //COLOCA VL UNITÃ�RIO
        $wBoxVl = $w*0.13;
        $xBoxVl = $wBoxUn + $xBoxUn;
        $texto = "VALOR";
        $this->pTextBox($xBoxVl, $y, $wBoxVl, $hLinha, $texto, $aFontCabProdutos, 'T', 'L', 0, '', false);
        
        //COLOCA VL TOTAL
        $wBoxTotal = $w*0.13;
        $xBoxTotal = $wBoxVl + $xBoxVl;
        $texto = "TOTAL";
        $this->pTextBox($xBoxTotal, $y, $wBoxTotal, $hLinha, $texto, $aFontCabProdutos, 'T', 'L', 0, '', false);
        
        // LISTA DE PRODUTOS
        $hBoxLinha = $this->hBoxLinha;
        $hMaxLinha = $this->hMaxLinha;
        $cont = 0;
        $aFontProdutos = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');

        if ($qtdItens > 0) {
            foreach ($this->det as $detI) {
                $thisItem   = $detI;
                $prod       = $thisItem->getElementsByTagName("prod")->item(0);
                $nitem      = $thisItem->getAttribute("nItem");
                $cProd      = $this->pSimpleGetValue($prod, "cProd");
                $xProd      = $this->pSimpleGetValue($prod, "xProd");
                $qCom       = number_format($this->pSimpleGetValue($prod, "qCom"), 2, ",", ".");
                $uCom       = $this->pSimpleGetValue($prod, "uCom");
                $vUnCom     = number_format($this->pSimpleGetValue($prod, "vUnCom"), 2, ",", ".");
                $vProd      = number_format($this->pSimpleGetValue($prod, "vProd"), 2, ",", ".");

                //COLOCA PRODUTO
                $yBoxProd = $y + $hLinha + ($cont*$hMaxLinha);

                //COLOCA PRODUTO CÃ“DIGO
                $wBoxCod = $w*0.17;
                $texto = $cProd;
                $this->pTextBox($x, $yBoxProd, $wBoxCod, $hMaxLinha, $texto, $aFontProdutos, 'C', 'C', 0, '', false);

                //COLOCA PRODUTO DESCRIÃ‡ÃƒO
                $wBoxDescricao = $w*0.43;
                $xBoxDescricao = $wBoxCod + $x;
                $texto = $xProd;
                $this->pTextBox(
                    $xBoxDescricao,
                    $yBoxProd,
                    $wBoxDescricao,
                    $hMaxLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'L',
                    0,
                    '',
                    false
                );

                //COLOCA PRODUTO QUANTIDADE
                $wBoxQt = $w*0.08;
                $xBoxQt = $wBoxDescricao + $xBoxDescricao;
                $texto = $qCom;
                $this->pTextBox(
                    $xBoxQt,
                    $yBoxProd,
                    $wBoxQt,
                    $hMaxLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'C',
                    0,
                    '',
                    false
                );

                //COLOCA PRODUTO UNIDADE
                $wBoxUn = $w*0.06;
                $xBoxUn = $wBoxQt + $xBoxQt;
                $texto = $uCom;
                $this->pTextBox(
                    $xBoxUn,
                    $yBoxProd,
                    $wBoxUn,
                    $hMaxLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'C',
                    0,
                    '',
                    false
                );

                //COLOCA PRODUTO VL UNITÃ�RIO
                $wBoxVl = $w*0.13;
                $xBoxVl = $wBoxUn + $xBoxUn;
                $texto = $vUnCom;
                $this->pTextBox(
                    $xBoxVl,
                    $yBoxProd,
                    $wBoxVl,
                    $hMaxLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'R',
                    0,
                    '',
                    false
                );

                //COLOCA PRODUTO VL TOTAL
                $wBoxTotal = $w*0.13;
                $xBoxTotal = $wBoxVl + $xBoxVl;
                $texto = $vProd;
                $this->pTextBox(
                    $xBoxTotal,
                    $yBoxProd,
                    $wBoxTotal,
                    $hMaxLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'R',
                    0,
                    '',
                    false
                );
                
                $cont++;
            }
        }
    }
    
    protected function pTotalDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $hLinha = 3;
        $wColEsq = ($maxW*0.7);
        $wColDir = ($maxW*0.3);
        $xValor = $x + $wColEsq;
        $qtdItens = $this->det->length;
        
        // RECEBE VALORES
        $vProd = $this->pSimpleGetValue($this->ICMSTot, "vProd");
        $vNF = $this->pSimpleGetValue($this->ICMSTot, "vNF");
        $vDesc  = $this->pSimpleGetValue($this->ICMSTot, "vDesc");
        // VER NA LEI A OBRIGATORIEDADE
        //$vTotTrib = $this->number_format(pSimpleGetValue($this->ICMSTot, "vTotTrib"), 2, ",", ".");
        
        //COLOCA QUANTIDADE DE ITENS
        $texto = "Qtd. Total de Itens";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $wColEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = $qtdItens;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($xValor, $y, $wColDir, $hLinha, $texto, $aFont, 'T', 'R', 0, '', false);
        
        //COLOCA TOTAL
        $yTotal = $y + ($hLinha);
        $texto = "Total de Produtos";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $yTotal, $wColEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = "R$ " . number_format($vProd, 2, ",", ".");
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($xValor, $yTotal, $wColDir, $hLinha, $texto, $aFont, 'T', 'R', 0, '', false);

        //COLOCA DESCONTO
        $yDesconto = $y + ($hLinha*2);
        $texto = "Descontos";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $yDesconto, $wColEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = "R$ " . $vDesc;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($xValor, $yDesconto, $wColDir, $hLinha, $texto, $aFont, 'T', 'R', 0, '', false);

        //COLOCA TOTAL FINAL
        $yTotalFinal = $y + ($hLinha*3);
        $texto = "Total";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $yTotalFinal, $wColEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = "R$ " . $vNF;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($xValor, $yTotalFinal, $wColDir, $hLinha, $texto, $aFont, 'T', 'R', 0, '', false);

        // VER NA LEI A OBRIGATORIEDADE
        //COLOCA TOTAL TRIBUTOS
        /*
        $yTotalFinal = $y + ($hLinha*4);
        $texto = "InformaÃ§Ã£o dos Tributos Totais Incidentes";
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $this->pTextBox($x, $yTotalFinal, $wColEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);
        $texto = "R$ " . $vTotTrib;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($xValor, $yTotalFinal, $wColDir, $hLinha, $texto, $aFont, 'T', 'R', 0, '', false);
         */

    }
    
    protected function pPagamentosDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $qtdPgto = $this->pag->length;
        $w = ($maxW*1);
        $hLinha = $this->hLinha;
        $wColEsq = ($maxW*0.7);
        $wColDir = ($maxW*0.3);
        $xValor = $x + $wColEsq;
        $aFontPgto = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        
        //COLOCA FORMA DE PAGAMENTO
        $wBoxEsq = $w*0.7;
        $texto = "FORMA DE PAGAMENTO";
        $this->pTextBox($x, $y, $wBoxEsq, $hLinha, $texto, $aFontPgto, 'T', 'L', 0, '', false);
        
        //COLOCA VALOR PAGO
        $wBoxDir = $w*0.3;
        $xBoxDescricao = $x + $wBoxEsq;
        $texto = "VALOR PAGO";
        $this->pTextBox($xBoxDescricao, $y, $wBoxDir, $hLinha, $texto, $aFontPgto, 'T', 'R', 0, '', false);
        
        // LISTA DE PAGAMENTOS
        $cont = 0;
        
        if ($qtdPgto > 0) {
            foreach ($this->pag as $pagI) {
                $tPag = $this->pSimpleGetValue($pagI, "tPag");
                $tPagNome = $this->tipoPag($tPag);
                $tPnome = $tPagNome;
                $vPag = number_format($this->pSimpleGetValue($pagI, "vPag"), 2, ",", ".");
                $card = $pagI->getElementsByTagName("card")->item(0);
                $cardCNPJ = '';
                $tBand = '';
                $tBandNome = '';
                if (isset($card)) {
                    $cardCNPJ = $this->pSimpleGetValue($card, "CNPJ");
                    $tBand    = $this->pSimpleGetValue($card, "tBand");
                    $cAut = $this->pSimpleGetValue($card, "cAut");
                    $tBandNome = self::getCardName($tBand);
                }
                //COLOCA PRODUTO
                $yBoxProd = $y + $hLinha + ($cont*$hLinha);

                //COLOCA PRODUTO CÃ“DIGO
                $texto = $tPagNome;
                $this->pTextBox($x, $yBoxProd, $wBoxEsq, $hLinha, $texto, $aFont, 'T', 'L', 0, '', false);

                //COLOCA PRODUTO DESCRIÃ‡ÃƒO
                $xBoxDescricao = $wBoxEsq + $x;
                $texto = "R$ " . $vPag;
                $this->pTextBox(
                    $xBoxDescricao,
                    $yBoxProd,
                    $wBoxDir,
                    $hLinha,
                    $texto,
                    $aFontProdutos,
                    'C',
                    'R',
                    0,
                    '',
                    false
                );

                $cont++;
            }
        }
    }
    
    protected function pFiscalDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $w = ($maxW*1);
        $hLinha = $this->hLinha;
        $aFontTit = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $aFontTex = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        
        // RECEBE VALORES
        $digVal = $this->pSimpleGetValue($this->nfe, "DigestValue");
        $chNFe = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        $tpAmb = $this->pSimpleGetValue($this->ide, 'tpAmb');
        $cUF = $this->pSimpleGetValue($this->ide, 'cUF');
        $nNF = $this->pSimpleGetValue($this->ide, 'nNF');
        $serieNF = str_pad($this->pSimpleGetValue($this->ide, "serie"), 3, "0", STR_PAD_LEFT);
        $dhEmi = $this->pSimpleGetValue($this->ide, "dhEmi");
        $urlChave = $this->urlConsulta[$tpAmb][$cUF]['chave'];
        
        //COLOCA TITULO
        $texto = "Ã�REA DE MENSAGEM FISCAL";
        $this->pTextBox($x, $y, $w, $hLinha, $texto, $aFontTit, 'C', 'C', 0, '', false);
        //COLOCA TEXTO 1
        $yTex1 = $y + ($hLinha*1);
        $hTex1 = $hLinha*2;
        $texto = "NÃºmero " . $nNF . " SÃ©rie " . $serieNF . " " .$dhEmi . " - Via Consumidor";
        $this->pTextBox($x, $yTex1, $w, $hTex1, $texto, $aFontTex, 'C', 'C', 0, '', false);
        //COLOCA TEXTO 2
        $yTex2 = $y + ($hLinha*3);
        $hTex2 = $hLinha*2;
        $texto = "Consulte pela Chave de Acesso em " . $urlChave;
        $this->pTextBox($x, $yTex2, $w, $hTex2, $texto, $aFontTex, 'C', 'C', 0, '', false);
        //COLOCA TITULO 2
        $texto = "CHAVE DE ACESSO";
        $yTit2 = $y + ($hLinha*5);
        $this->pTextBox($x, $yTit2, $w, $hLinha, $texto, $aFontTit, 'C', 'C', 0, '', false);
        //COLOCA TEXTO 2
        $yTex3 = $y + ($hLinha*6);
        $texto = $chNFe;
        $this->pTextBox($x, $yTex3, $w, $hLinha, $texto, $aFontTex, 'C', 'C', 0, '', false);
        
    }
    
    protected function pConsumidorDANFE($x = 0, $y = 0, $h = 0)
    {

        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $w = ($maxW*1);
        $hLinha = $this->hLinha;
        $aFontTit = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $aFontTex = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        
        //COLOCA TITULO
        $texto = "CONSUMIDOR";
        $this->pTextBox($x, $y, $w, $hLinha, $texto, $aFontTit, 'C', 'C', 0, '', false);
        
        
        // RECEBE VALORES
        if (isset($this->dest)) {
            $considEstrangeiro = !empty($this->dest->getElementsByTagName("idEstrangeiro")->item(0)->nodeValue)
                    ? $this->dest->getElementsByTagName("idEstrangeiro")->item(0)->nodeValue
                    : '';
            $consCPF = !empty($this->dest->getElementsByTagName("CPF")->item(0)->nodeValue)
                    ? $this->dest->getElementsByTagName("CPF")->item(0)->nodeValue
                    : '';
            $consCNPJ = !empty($this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue)
                    ? $this->dest->getElementsByTagName("CNPJ")->item(0)->nodeValue
                    : '';
            $cDest = $consCPF.$consCNPJ.$considEstrangeiro; //documentos do consumidor
        
            $enderDest = $this->dest->getElementsByTagName("enderDest")->item(0);
            $consNome = $this->pSimpleGetValue($this->dest, "xNome");
            $consLgr = $this->pSimpleGetValue($enderDest, "xLgr");
            $consNro = $this->pSimpleGetValue($enderDest, "nro");
            $consCpl = $this->pSimpleGetValue($enderDest, "xCpl", " - ");
            $consBairro = $this->pSimpleGetValue($enderDest, "xBairro");
            $consCEP = $this->pFormat($this->pSimpleGetValue($enderDest, "CEP"));
            $consMun = $this->pSimpleGetValue($enderDest, "xMun");
            $consUF = $this->pSimpleGetValue($enderDest, "UF");
            $considEstrangeiro = $this->pSimpleGetValue($this->dest, "idEstrangeiro");
            $consCPF = $this->pSimpleGetValue($this->dest, "CPF");
            $consCNPJ = $this->pSimpleGetValue($this->dest, "CNPJ");
            $consDoc = $consCPF.$consCNPJ.$considEstrangeiro; //documentos do consumidor
            
            $yTex1 = $y + $hLinha;
            $texto = $consNome ." - ". $consDoc . "\n" . $consLgr . "," . $consNro . " "
                    . $consCpl . "," . $consBairro . ". CEP:" . $consCEP . ". " . $consMun . "-" . $consUF;
            $this->pTextBox($x, $yTex1, $w, $hLinha*3, $texto, $aFontTex, 'C', 'C', 0, '', false);
        } else {
            //COLOCA TITULO
            $yTex1 = $y + $hLinha;
            $texto = "Consumidor nÃ£o identificado";
            $this->pTextBox($x, $yTex1, $w, $hLinha, $texto, $aFontTex, 'C', 'C', 0, '', false);
        }
        
    }
    
    protected function pQRDANFE($x = 0, $y = 0, $h = 0)
    {
        // CONFIGURAÃ‡ÃƒO DE POSIÃ‡ÃƒO
        $margemInterna = $this->margemInterna;
        $maxW = $this->wPrint;
        $w = ($maxW*1);
        $hLinha = $this->hLinha;
        $hBoxLinha = $this->hBoxLinha;
        $aFontTit = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $aFontTex = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $dhRecbto = '';
        $nProt = '';

        if (isset($this->nfeProc)) {
            $nProt = $this->pSimpleGetValue($this->nfeProc, "nProt");
            $dhRecbto  = $this->pSimpleGetValue($this->nfeProc, "dhRecbto");
        }
        $qrcode = new QRcode($this->qrCode, 'M');
        $wQr = 50;
        $hQr = 50;
        $yQr = ($y+$margemInterna);
        $xQr = ($w/2) - ($wQr/2);
        $qrcode->displayFPDF($this->pdf, $xQr, $yQr, $wQr);
        $yQr = ($yQr+$hQr+$margemInterna);
        $this->pTextBox($x, $yQr, $w, $hBoxLinha, "Protocolo de AutorizaÃ§Ã£o: " . $nProt . "\n"
                . $dhRecbto, $aFontTex, 'C', 'C', 0, '', false);
    }
   
    /**
     * printDANFE
     * Esta funÃ§Ã£o envia a DANFE em PDF criada para o dispositivo informado.
     * O destino da impressÃ£o pode ser :
     * I-browser
     * D-browser com download
     * F-salva em um arquivo local com o nome informado
     * S-retorna o documento como uma string e o nome Ã© ignorado.
     * Para enviar o pdf diretamente para uma impressora indique o
     * nome da impressora e o destino deve ser 'S'.
     *
     * @param  string $nome    Path completo com o nome do arquivo pdf
     * @param  string $destino DireÃ§Ã£o do envio do PDF
     * @param  string $printer IdentificaÃ§Ã£o da impressora no sistema
     * @return string Caso o destino seja S o pdf Ã© retornado como uma string
     * @todo   Rotina de impressÃ£o direta do arquivo pdf criado
     */
    public function printDANFE($nome = '', $destino = 'I', $printer = '')
    {
        $arq = $this->pdf->Output($nome, $destino);
        if ($destino == 'S') {
            //aqui pode entrar a rotina de impressÃ£o direta
        }
        return $arq;

    } //fim funÃ§Ã£o printDANFE
    
    
    /**
     * str2Hex
     * Converte string para haxadecimal ASCII
     *
     * @param  string $str
     * @return string
     */
    protected static function str2Hex($str)
    {
        if ($str == '') {
            return '';
        }
        $hex = "";
        $iCount = 0;
        do {
            $hex .= sprintf("%02x", ord($str{$iCount}));
            $iCount++;
        } while ($iCount < strlen($str));
        return $hex;
    }//fim str2Hex
    
    protected static function getCardName($tBand)
    {
        switch ($tBand) {
            case '01':
                $tBandNome = 'VISA';
                break;
            case '02':
                $tBandNome = 'MASTERCARD';
                break;
            case '03':
                $tBandNome = 'AMERICAM EXPRESS';
                break;
            case '04':
                $tBandNome = 'SOROCRED';
                break;
            case '99':
                $tBandNome = 'OUTROS';
        }
        return $tBandNome;
    }//fim getCardName
    
    protected function tipoPag($tPag)
    {
        switch ($tPag) {
            case '01':
                $tPagNome = 'Dinheiro';
                break;
            case '02':
                $tPagNome = 'Cheque';
                break;
            case '03':
                $tPagNome = 'CartÃ£o de CrÃ©dito';
                break;
            case '04':
                $tPagNome = 'CartÃ£o de DÃ©bito';
                break;
            case '05':
                $tPagNome = 'CrÃ©dito Loja';
                break;
            case '10':
                $tPagNome = 'Vale AlimentaÃ§Ã£o';
                break;
            case '11':
                $tPagNome = 'Vale RefeiÃ§Ã£o';
                break;
            case '12':
                $tPagNome = 'Vale Presente';
                break;
            case '13':
                $tPagNome = 'Vale CombustÃ­vel';
                break;
            case '99':
                $tPagNome = 'Outros';
        }
        return $tPagNome;
    }
    
    /**
     * hex2Str
     * Converte hexadecimal ASCII para string
     *
     * @param  string $str
     * @return string
     */
    protected static function hex2Str($str)
    {
        if ($str == '') {
            return '';
        }
        $bin = "";
        $iCount = 0;
        do {
            $bin .= chr(hexdec($str{$iCount}.$str{($iCount + 1)}));
            $iCount += 2;
        } while ($iCount < strlen($str));
        return $bin;
    }

    protected function makeQRCode(
        $chNFe,
        $url,
        $tpAmb,
        $cDest = '',
        $dhEmi = '',
        $vNF = '',
        $vICMS = '',
        $digVal = '',
        $idToken = '000001',
        $token = ''
    ) {
        $nVersao = '100'; //versÃ£o do QRCode
        
        $dhHex = self::str2Hex($dhEmi);
        $digHex = self::str2Hex($digVal);
        
        $seq = '';
        $seq .= 'chNFe=' . $chNFe;
        $seq .= '&nVersao=' . $nVersao;
        $seq .= '&tpAmb=' . $tpAmb;
        if ($cDest != '') {
            $seq .= '&cDest=' . $cDest;
        }
        $seq .= '&dhEmi=' . strtolower($dhHex);
        $seq .= '&vNF=' . $vNF;
        $seq .= '&vICMS=' . $vICMS;
        $seq .= '&digVal=' . strtolower($digHex);
        $seq .= '&cIdToken=' . $idToken;

        //o hash code Ã© calculado com o Token incluso
        $hash = sha1($seq.$token);
        $seq .= '&cHashQRCode='. strtoupper($hash);
        if (strpos($url, '?') === false) {
            $seq = $url.'?'.$seq;
        } else {
            $seq = $url.''.$seq;
        }
        return $seq;
    }


    public function monta($orientacao = '', $papel = '', $logoAlign = 'C')
    {
        
    }

    public function printDocument($nome = '', $destino = 'I', $printer = '')
    {
        
    }
}
