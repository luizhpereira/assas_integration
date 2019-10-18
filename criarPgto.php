<?php
/*
 * Github: @luizhpereira
 * Autor: Luiz Henrique Pereira
 * Contato: luizpereiragit@gmail.com
 *
 * Criação de pagamentos consumindo API Asaas www.asaas.com
 * Documentação: https://asaasv3.docs.apiary.io
 */
session_start();
// fazer os respectivos includes dos devidos controladores criados para tratar as informações na sua aplicação
include("../../modelo/util/Configuracao.php");
include(Configuracao::getBaseDir()."modelo/util/Util.php");
include(Configuracao::getBaseDir()."modelo/afiliado/ControladorAfiliado.php");
include(Configuracao::getBaseDir()."modelo/endereco/ControladorEndereco.php");
include(Configuracao::getBaseDir()."modelo/afiliado_plano/ControladorAfiliado_Plano.php");
include(Configuracao::getBaseDir()."modelo/afiliado_pagamento/ControladorAfiliado_Pagamento.php");

/*Consultas para obter informações de seus usuários/clientes
 * Aqui relacionei 3 tabelas no banco, Afiliados, Endereço e Pagamento
 * porém você pode utilizar a estrutura que preferir em seus dados */
$objAfiliado = ControladorAfiliado::buscarPorId($_SESSION['afiliadoId']);
$objEndereco = ControladorEndereco::buscarPorReferencia($_SESSION['afiliadoId']);
$arrRetornoPagamento = ControladorAfiliado_Pagamento::listarPorAfiliado($_POST['userId']);

$name = $objAfiliado->getNome();
$email = $objAfiliado->getEmail();
$phone = $objAfiliado->getTelefone();
$cpfCnpj = Util::limpaCPF_CNPJ( $objAfiliado->getDocumento());
$postalCode = $objEndereco->getCep();
$address = $objEndereco->getLogradouro();
$addressNumber = $objEndereco->getNumero();
$complement = $objEndereco->getComplemento();
$province = $objEndereco->getBairro();
$municipalInscription = 0;
$stateInscription = 0;
/* estas informações foram passadas previamente pelo form através de POST.
 * Trata-se de forma de pagamento, vencimento e possíveis descrições além do Id do seu usuário criado na API asaas */
$customer = $_POST['asaas']; // id asaas se seu usuário
$billingType = $_POST['tipoPagamento']; //cartão ou boleto bancário
$dueDate = $_POST['vencimento'];
$value = $_POST['planoAtivo'];
$description = $_POST['descricao'];
$externalReference = $_POST['protocolo']; //escolha feita por mim para facilitar mapeamento dos pagamentos, trata-se de uma descrição comum.

if ($billingType == 'CREDIT_CARD') {
    
    $value = (($value*10)/100)+$value;    
}

//CADASTRANDO USUARIO NO ASAAS
if (!$customer) {
    
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://www.asaas.com/api/v3/customers");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    
    curl_setopt($ch, CURLOPT_POST, TRUE);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{
     \"name\": \"$name\",
     \"email\": \"$email\",
     \"phone\": \"$phone\",
     \"mobilePhone\": \"$phone\",
     \"cpfCnpj\": \"$cpfCnpj\",
     \"postalCode\": \"$postalCode\",
     \"address\": \"$address\",
     \"addressNumber\": \"$addressNumber\",
     \"complement\": \"$complement\",
     \"province\": \"$province\",
     \"externalReference\": \"$externalReference\",
     \"notificationDisabled\": false,
     \"additionalEmails\": \"$email\",
     \"municipalInscription\": \"$municipalInscription\",
     \"stateInscription\": \"$stateInscription\"
     }");
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "access_token: _seu_token_comunicacao_asaas_"
    ));
    
    $CustomerResponse = curl_exec($ch);
    curl_close($ch);

    $CustomerResponse = json_decode($CustomerResponse);

    $customer = $CustomerResponse->id;
    
    if ($CustomerResponse->errors) 
    {        
        //Adicionar método para exibir possíveis mensagens de erro no processo de criação de ID no ASAAS.
        $CustomerResponse->errors[0]->description; // como chamar a descrição do erro existente.        
    }
}

// CANCELANDO COBRANÇAS CADASTRADAS ANTERIORMENTE (PENDENTES, VENCIDAS OU DUPLICADAS) 

if ($arrRetornoPagamento) {
    
    //importando classe básica de pagamento
    include_once(Configuracao::getBaseDir()."modelo/afiliado_pagamento/Afiliado_Pagamento.php");
    //laço para verificação do status dos pagamentos
    foreach ($arrRetornoPagamento as $retornoPagamento)
    {

        if ($retornoPagamento->getIdPlano() != 0){
            
            $idPgtoAsaas = $retornoPagamento->getCodPagamento(); //ID do pagamento gerado através da API Asaas
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, "https://www.asaas.com/api/v3/payments/".$idPgtoAsaas."");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "access_token: _seu_token_comunicacao_asaas_"
            ));
            
            $cancelamentoResponse = curl_exec($ch);
            curl_close($ch);
            
            $cancelamentoResponse = json_decode($cancelamentoResponse);
            
            // adicionar aqui o tratamento que pretende fazer com as informações retornadas da API  
            
            if ($cancelamentoResponse->errors)
            {
                //Adicionar método para exibir possíveis mensagens de erro no processo de criação de ID no ASAAS.
                $cancelamentoResponse->errors[0]->description; // como chamar a descrição do erro existente.
            }
        
        }
    
    }
}

//GERANDO BOLETO DO USUARIO CRIADO NO ASAAS - NO PASSO ANTERIOR

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://www.asaas.com/api/v3/payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);

curl_setopt($ch, CURLOPT_POST, TRUE);

curl_setopt($ch, CURLOPT_POSTFIELDS, "{
  \"customer\": \"$customer\",
  \"billingType\": \"$billingType\",
  \"dueDate\": \"$dueDate\",
  \"value\": $value,
  \"description\": \"$description\",
  \"externalReference\": \"$externalReference\",
  \"discount\": {
    \"value\": 0,
    \"dueDateLimitDays\": 0
  },
  \"fine\": {
    \"value\": 0
  },
  \"interest\": {
    \"value\": 0
  }
}");

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/json",
    "access_token: _seu_token_comunicacao_asaas_"
));

$paymentResponse = curl_exec($ch);
curl_close($ch);


$paymentResponse = json_decode($paymentResponse);

if ($paymentResponse->errors)
{
    //Adicionar método para exibir possíveis mensagens de erro no processo de criação do pagamento na API Asaas.    
    $paymentResponse->errors[0]->description; // como chamar a descrição do erro existente.
}

// INPUT DE TODAS AS INFORMAÇÕES NO BANCO DE DADOS

if ($billingType == 'CREDIT_CARD') {
    
    // adicionar métodos para caso de compra através do cartão de crédito. EX:
    $docPagamento = 'CARTAO';
    $boletoUrl = $paymentResponse->invoiceUrl;
    $_SESSION['urlAsaas']=$paymentResponse->invoiceUrl;
    
} else {
    // adicionar métodos para caso de compra em boleto bancário. EX:
    $docPagamento = $paymentResponse->id;
    $boletoUrl = $paymentResponse->bankSlipUrl;
    $_SESSION['urlAsaas']=$paymentResponse->bankSlipUrl;
}

?>



