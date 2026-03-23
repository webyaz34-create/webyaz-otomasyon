---
description: How to deploy a new version of the Webyaz Otomasyon plugin to GitHub
---

# Deploy Workflow

Bu workflow, yeni değişiklikler yapıldıktan sonra eklentiyi GitHub'a yükler ve otomatik güncelleme sistemini tetikler.

## Adımlar

1. `webyaz-otomasyon.php` dosyasındaki `Version:` header'ını ve `WEBYAZ_VERSION` sabitini bir sonraki semantic version'a artır (örn: 4.0.0 → 4.1.0)

// turbo
2. `git add -A` komutu ile tüm değişiklikleri staging'e al

// turbo
3. `git commit -m "feat: [değişiklik açıklaması]"` ile commit at

// turbo
4. `git push origin main` ile GitHub'a push et

5. GitHub Actions otomatik olarak:
   - `update-info.json` dosyasını yeni versiyon ile günceller
   - Plugin klasörünü ZIP'ler
   - GitHub Release oluşturur
   - Müşteri siteleri 12 saat içinde (veya elle kontrol edildiğinde) güncellemeyi görür
