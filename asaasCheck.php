<?php
/*
 * Github: @luizhpereira
 * Autor: Luiz Henrique Pereira
 * Contato: luizpereiragit@gmail.com
 * 
 * Autocheck para atualização de status e integração/comunicação com API de pagamento https://www.asaas.com
 * Documentação: https://asaasv3.docs.apiary.io
 */
session_start();

//exemplo de consulta dos status salvos no banco de dados.
$arrObjPagamento = ControladorAfiliado_Pagamento::listarPorSituacao("PENDING");


if ($arrObjPagamento) {
    //laço 
    foreach($arrObjPagamento as $retorno)
    {
        //código do cliente que é servido pela API Asaas e deve ser armazenado no banco de dados.
        $customer = $retorno->getCodigo();
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, "https://www.asaas.com/api/v3/payments?customer=".$customer."");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "access_token: _seu_token_comunicacao_asaas_"
        ));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $response = json_decode($response);
        
        
        
        if ($response->data[0]->status == "CONFIRMED" || $response->data[0]->status == "RECEIVED_IN_CASH" || $response->data[0]->status == "RECEIVED")
        {
            //input dos métodos após a confirmação do pagamento.            
        }
        
        if ($response->data[0]->status == "OVERDUE" || $response->data[0]->status == "REFUNDED" || $response->data[0]->status == "CHARGEBACK_REQUESTED")
        {
            /* Cancelamento dos status que estão vencidos ou abortados por algum motivo 
             * status cancelado não é mais servido pela API a menos que seja feita uma consulta específica do status */
            $idPgtoAsaas = $retorno->getCodPagamento();
            
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
            
            /* Adicionar métodos após a confirmação da alteração dos status para cancelado na API Asaas */
            
            if ($cancelamentoResponse->errors)
            {
                
                //Adicionar método para exibir possíveis mensagens de erro no processo de alteração de status para cancelado.
  
            }
        }
    }
}

?>
        