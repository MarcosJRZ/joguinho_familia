# 🎭 Descobrir o Impostor - Jogo

Um jogo divertido para toda família onde um impostor precisa passar despercebido!

## 🚀 Como Funcionar

1. **Escolha um tema** - O jogo oferece 10 temas gerados por IA
2. **Configure os jogadores** - De 3 a 10 jogadores podem participar
3. **Distribua as palavras** - Cada jogador recebe uma palavra, exceto o impostor que recebe uma dica
4. **Descubra o impostor** - Os jogadores discutem e tentam descobrir quem é o impostor!

## 🛠️ Instalação

1. Clone o repositório
2. Execute `composer install` (já executado)
3. Configure a API key do Groq (veja abaixo)
4. Inicie o servidor com `php artisan serve`

## 🤖 Configuração da IA (Groq API)

O jogo usa a **Groq API** (gratuita) para gerar temas e palavras automaticamente.

### Passo a passo para obter a API key:

1. **Acesse**: https://console.groq.com/
2. **Faça login** ou **crie uma conta gratuita**
3. **Vá para "API Keys"** no menu lateral
4. **Clique em "Create API Key"**
5. **Dê um nome** para sua chave (ex: "Jogo Impostor")
6. **Copie a API key** gerada

### Configurar no projeto:

1. Abra o arquivo `.env`
2. Encontre a linha: `GROQ_API_KEY=`
3. Cole sua API key: `GROQ_API_KEY=gsk_sua_chave_aqui`
4. Salve o arquivo

### ⚠️ Importante:
- A Groq API é **100% gratuita**
- Oferece **rate limits generosos** 
- **Não precisa cartão de crédito**
- Usa modelos **Llama 3** de alta qualidade

## 🎮 Funcionalidades

✅ **Geração automática de temas** com IA  
✅ **Interface moderna** com tema escuro  
✅ **Responsivo** para mobile e desktop  
✅ **Animações suaves** e efeitos visuais  
✅ **Distribuição automática** de palavras  
✅ **Escolha aleatória** do impostor  
✅ **Sistema de fallback** caso a IA falhe  

## 🎨 Design

- **Tema escuro moderno**
- **Gradientes e efeitos** de vidro
- **Animações CSS** fluidas
- **Layout responsivo**
- **Tipografia Inter**

## 🔧 Tecnologias

- **Laravel 11**
- **PHP 8.2+**
- **Groq API (Llama 3)**
- **CSS3 + Animações**
- **JavaScript (Vanilla)**
- **Design Responsivo**

## 📱 Como Jogar

1. **Selecione um tema** da lista
2. **Defina quantos jogadores** vão participar (3-10)
3. **Clique em "Iniciar Jogo"**
4. **Cada jogador vê sua palavra/dica** secretamente
5. **Um jogador é escolhido para começar**
6. **Discutam e tentem descobrir o impostor!**

## 🎯 Regras

- **Jogadores normais**: Recebem a palavra do tema
- **O impostor**: Recebe uma dica relacionada (mas diferente)
- **Objetivo dos jogadores**: Descobrir quem é o impostor
- **Objetivo do impostor**: Passar despercebido

## 🔄 Exemplo de Funcionamento

**Tema**: Animais  
**Palavra dos jogadores**: "Cachorro"  
**Dica do impostor**: "Animal de estimação"  

O impostor deve tentar participar da conversa sem revelar que não sabe a palavra exata!

## ⚡ Performance

- **Sem banco de dados** - Jogo simples e rápido
- **Cache em arquivo** - Sessões persistentes
- **Fallback local** - Funciona mesmo sem internet
- **Otimizado** para dispositivos móveis

## 🤝 Contribuição

Sinta-se à vontade para contribuir com melhorias, novos temas ou funcionalidades!

---

**Divirta-se jogando! 🎉**