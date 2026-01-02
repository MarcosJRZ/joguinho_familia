# Estrutura de Arquivos do Projeto

## 📁 Organização Frontend

### **CSS**
- `resources/css/game.css` - Estilos específicos do jogo de família
- `resources/css/app.css` - Estilos globais do Tailwind CSS

### **JavaScript** 
- `resources/js/game.js` - Lógica do jogo (AJAX, DOM, estados)
- `resources/js/app.js` - Scripts gerais da aplicação

### **Views**
- `resources/views/game/index.blade.php` - Template principal do jogo (HTML limpo)

## 🔧 Configurações

### **Vite**
- `vite.config.js` - Configuração do bundler
- Compila e otimiza CSS/JS automaticamente
- Assets gerados em `public/build/`

## 🎯 Como Funciona

### **Desenvolvimento**
```bash
npm run dev    # Hot reload para desenvolvimento
```

### **Produção**
```bash
npm run build  # Compila assets otimizados
```

### **Assets no Blade**
```php
@vite(['resources/css/game.css', 'resources/js/game.js'])
```

## ✅ Benefícios da Reorganização

1. **Separação de responsabilidades**
2. **Facilita manutenção do código**
3. **Melhora performance com bundling**
4. **Cacheable assets em produção**
5. **Hot reload em desenvolvimento**
6. **Código mais limpo e organizad**