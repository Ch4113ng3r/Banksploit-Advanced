#!/usr/bin/env node

const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

async function convertHtmlToPdf() {
    const htmlFile = path.join(__dirname, 'src', 'index.html');
    const outputFile = path.join(__dirname, 'BankSploit_CTF_Guide.pdf');

    if (!fs.existsSync(htmlFile)) {
        console.error(`Error: ${htmlFile} not found`);
        process.exit(1);
    }

    console.log(`[*] Converting: ${htmlFile}`);
    console.log(`[*] Output: ${outputFile}`);

    try {
        const browser = await puppeteer.launch({ headless: 'new' });
        const page = await browser.newPage();

        // Set viewport for better rendering
        await page.setViewport({ width: 1920, height: 1080 });

        // Load the HTML file
        const fileUrl = `file://${htmlFile.replace(/\\/g, '/')}`;
        await page.goto(fileUrl, { waitUntil: 'networkidle2', timeout: 30000 });

        console.log(`[*] Page loaded, rendering to PDF...`);

        // Generate PDF with all pages
        await page.pdf({
            path: outputFile,
            format: 'A4',
            margin: { top: '0.5in', right: '0.5in', bottom: '0.5in', left: '0.5in' },
            printBackground: true,
            preferCSSPageBreaks: true,
        });

        await browser.close();

        console.log(`[✓] PDF created successfully: ${outputFile}`);
        console.log(`[✓] File size: ${(fs.statSync(outputFile).size / 1024 / 1024).toFixed(2)} MB`);
    } catch (error) {
        console.error(`[X] Conversion failed: ${error.message}`);
        process.exit(1);
    }
}

convertHtmlToPdf();
