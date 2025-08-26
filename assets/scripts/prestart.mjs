import { readFileSync, writeFileSync } from 'fs';

const [,, templatePath, outputPath] = process.argv;

if (!templatePath || !outputPath) {
    console.error('Usage: node prestart.mjs <template-path> <output-path>');
    process.exit(1);
}

try {
    const template = readFileSync(templatePath, 'utf8');
    const config = template.replace(/\${(\w+)(?::-([^}]*))?}/g, (match, varName, defaultValue) => {
        return process.env[varName] || defaultValue || match;
    });
    
    writeFileSync(outputPath, config);
    console.log(`Configuration written to ${outputPath}`);
} catch (error) {
    console.error('Error processing template:', error);
    process.exit(1);
}