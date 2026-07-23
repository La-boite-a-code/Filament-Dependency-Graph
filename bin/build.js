import * as esbuild from 'esbuild'
import { copyFile, mkdir } from 'node:fs/promises'

await esbuild.build({
    entryPoints: {
        'components/dependency-graph': 'resources/js/dependency-graph.js',
    },
    outdir: 'dist',
    bundle: true,
    mainFields: ['module', 'main'],
    platform: 'neutral',
    format: 'esm',
    treeShaking: true,
    target: ['es2020'],
    minify: true,
})

await mkdir('dist', { recursive: true })
await copyFile('resources/css/dependency-graph.css', 'dist/filament-dependency-graph.css')

process.stdout.write('Assets built into dist/\n')
