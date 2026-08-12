# Learn by Bluxor Landing Page SDK

This folder is the local starter kit for building static landing-page packages.

Production never runs package installs or build scripts from uploaded ZIPs. Build locally, upload only the finished package.

## Workflow

```bash
cd landing-page-sdk/starter
npm install
npm run dev
npm run build
npm run package
```

The package command writes:

```text
dist-package/landing-page.zip
```

Upload that ZIP in the Learn by Bluxor admin panel.

## Runtime Rule

Landing pages are presentation packages only. They call the platform SDK for product data, offers, checkout redirects, and analytics. Prices are never trusted from landing-page JavaScript.
