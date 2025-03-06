#!/bin/bash

# Caminhos das pastas
PASTA_CLIENTE="Client-1"
PASTA_CLIENT2="Client-2"
PASTA_PROVEDOR="Provedor"
TEMPO_ESPERA=30  # Tempo de espera em segundos

# Função para rodar cada serviço e manter o log visível
start_service() {
    local pasta="$1"
    echo "Iniciando $pasta..."
    (cd "/home/endrew/Documentos/asa/final_project/$pasta" && docker compose up) &
}

# Inicia o primeiro cliente
start_service "$PASTA_CLIENTE"

# Aguarda um tempo antes de iniciar o próximo
sleep $TEMPO_ESPERA

# Inicia o segundo cliente
start_service "$PASTA_CLIENT2"

# Aguarda um tempo antes de iniciar o Provedor
sleep $TEMPO_ESPERA

# Inicia o Provedor
start_service "$PASTA_PROVEDOR"

# Mantém o terminal rodando e esperando todos os serviços
wait
