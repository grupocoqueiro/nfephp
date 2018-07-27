<?php

namespace NFePHP\Common\Base;

/**
 * Classe base para o envio de emails tanto para NFe, NFCe, CTe e MDFe
 *
 * @category  NFePHP
 * @package   NFePHP\Common\Base\BaseMail
 * @copyright Copyright (c) 2008-2015
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/nfephp for the canonical source repository
 */

use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Mime\Mime;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use NFePHP\Common\Exception;
use NFePHP\Common\Files;

if (!defined('NFEPHP_ROOT')) {
    define('NFEPHP_ROOT', dirname(dirname(dirname(__FILE__))));
}

class BaseMail
{
    /**
     * $template
     *
     * @var string
     */
    protected $template = '';
    /**
     * $aMailConf
     *
     * @var array
     */
    protected $aMailConf = array();
    /**
     * $transport
     *
     * @var Zend\Mail\Transport\Smtp
     */
    protected $transport = '';
    /**
     * $aAttachments
     *
     * @var array
     */
    protected $aAttachments = array();
    /**
     * $content
     *
     * @var Zend\Mime\Message
     */
    protected $content = '';
    
    /**
     * __construct
     * Mâ©todo construtor configura o transporte do email
     *
     * @param  type $aMailConf
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($aMailConf = array())
    {
        if (count($aMailConf) == 0) {
            $msg = 'Os parâ¢metros de configuração para email devem ser passados em um array.';
            throw new Exception\InvalidArgumentException($msg);
        }
        $this->aMailConf = $aMailConf;
        //configura a forma de transporte no envio dos emails
        $aMuser = explode('@', $this->aMailConf['mailUser']);
        $domain = $aMuser[1];
        $connConfig = array();
        $connConfig['username'] = $this->aMailConf['mailUser'];
        $connConfig['password'] = $this->aMailConf['mailPass'];
        if ($this->aMailConf['mailProtocol'] != '') {
            $connConfig['ssl'] = $this->aMailConf['mailProtocol'];
        }
        $this->transport = new SmtpTransport();
        $options = new SmtpOptions(
            array(
                'name'              => $domain,
                'host'              => $this->aMailConf['mailSmtp'],
                'port'              => $this->aMailConf['mailPort'],
                'connection_class'  => 'plain',
                'connection_config' => $connConfig,
            )
        );
        $this->transport->setOptions($options);
    }

    /**
     * setTemplate
     * Carrega o arquivo html do template do email em um parametro da classe
     *
     * @param type $pathFile
     */
    public function setTemplate($pathFile = '')
    {
        if (is_file($pathFile)) {
            $this->template = Files\FilesFolders::readFile($pathFile);
        }
    }
    
    /**
     * addAttachment
     *
     * @param string $pathFile
     * @param string $filename
     */
    public function addAttachment($pathFile = '', $filename = '')
    {
    	$filename = $filename ?: uniqid() . date('YmdHis');
        $filename = self::zRemakeFilename($pathFile, $filename);
       
        
        if ( !is_file($pathFile) ) {
        	if ( !is_dir('tmp') ) {
        		mkdir(getcwd() . DIRECTORY_SEPARATOR . 'tmp', 0777, true);
	        	chmod(getcwd() . DIRECTORY_SEPARATOR . 'tmp', 0777);
        	}
        	$filename = 'tmp' . DIRECTORY_SEPARATOR . $filename.'.xml';
        	$file = fopen($filename, 'w');
        	fwrite($file, $pathFile);
        	fclose($file);
        }

        $fInfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file($fInfo, $filename);
        $file = fopen($filename, 'r');
        $attachment = new MimePart($file);
        $attachment->type = $mimeType;
        $attachment->encoding    = Mime::ENCODING_BASE64;
        $attachment->disposition = Mime::DISPOSITION_ATTACHMENT;
        $attachment->filename = $filename;
        $this->aAttachments[] = $attachment;
        
        if ( !is_file($pathFile) ) {
        	fclose($file);
        	unlink(getcwd() . DIRECTORY_SEPARATOR . $filename);
        }
    }
   
    /**
     * buildMessage
     *
     * @param string $msgHtml
     * @param string $msgTxt
     */
    public function buildMessage($msgHtml = '', $msgTxt = '')
    {
        //Html part
        $htmlPart = new MimePart($msgHtml);
        $htmlPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $htmlPart->type = "text/html; charset=UTF-8";
        //text part
        $textPart = new MimePart($msgTxt);
        $textPart->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
        $textPart->type = "text/plain; charset=UTF-8";
        //monatgem do conteâºdo da mensagem
        $this->content = new MimeMessage();
        $this->content->addPart($textPart);
        $this->content->addPart($htmlPart);
    }

    /**
     * sendMail
     *
     * @param string $subject
     * @param array  $aMail
     */
    public function sendMail($subject = '', $aMail = array())
    {
        $message = new Message();
        $message->setEncoding("UTF-8");
    
        $message->setFrom(
            $this->aMailConf['mailFromMail'],
            $this->aMailConf['mailFromName']
        );
        foreach ($aMail as $mail) {
            //destinatâ¡rios
            $message->addTo($mail);
        }
        //assunto
        $message->setSubject($subject);
        //cria o corpo da mensagem
        $body = new MimeMessage();
        $contentPart = new MimePart($this->content->generateMessage());
        $contentPart->type = 'multipart/alternative;' . PHP_EOL . ' boundary="' .
                $this->content->getMime()->boundary() . '"';
        $messageType = 'multipart/related';
        //adiciona o html e o txt
        $body->addPart($contentPart);
        foreach ($this->aAttachments as $attachment) {
            $body->addPart($attachment);
        }
        //monta o corpo
        $message->setBody($body);
        $message->getHeaders()->get('content-type')->setType($messageType);
        //enviar
        try {
            $this->transport->send($message);
        } catch (\Zend\Mail\Protocol\Exception\RuntimeException $e) {
            return $e;
        }
        return true;
    }
    
    /**
     * zRemakeFilename
     * Caso nâ£o seja passado um nome de arquivo entâ£o
     * pega o nome do arquivo do path
     *
     * @param  string $pathFile
     * @param  string $filename
     * @return string
     */
    private static function zRemakeFilename($pathFile = '', $filename = '')
    {
        if ($filename != '') {
            return $filename;
        }
        $delimiter = DIRECTORY_SEPARATOR;
        $aFile = explode($delimiter, $pathFile);
        $num = count($aFile);
        return $aFile[$num-1];
    }
}
