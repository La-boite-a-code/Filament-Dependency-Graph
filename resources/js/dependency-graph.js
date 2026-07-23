import cytoscape from 'cytoscape'
import dagre from 'cytoscape-dagre'
import fcose from 'cytoscape-fcose'

cytoscape.use(dagre)
cytoscape.use(fcose)

const labelMetrics = (() => {
    const context = document.createElement('canvas').getContext('2d')

    return (node) => {
        const label = String(node.data('label') ?? '')
        context.font = `${node.data('type') === 'panel' ? 'bold ' : ''}11px Helvetica, Arial, sans-serif`

        return Math.max(...label.split('\n').map((line) => context.measureText(line).width), 24)
    }
})()

// Sizing nodes from a canvas measurement instead of `width: 'label'`: the
// deprecated label sizing needs the renderer to have measured every label,
// which silently fails while the lazily mounted container has no dimensions
// and leaves nodes invisible.
const nodeWidth = (node) => Math.min(Math.ceil(labelMetrics(node)) + 8, 180)

// Normalizes any CSS color to rgb() by painting one pixel and reading it
// back: Filament ships its theme scales as oklch(), which browsers pass
// through unconverted in both getComputedStyle and the fillStyle getter,
// and Cytoscape cannot parse. An unparseable value leaves fillStyle on the
// fallback assigned just before, so the fallback survives.
const normalizeColor = (() => {
    const canvas = document.createElement('canvas')
    canvas.width = 1
    canvas.height = 1
    const context = canvas.getContext('2d', { willReadFrequently: true })

    return (value, fallback) => {
        context.fillStyle = fallback
        context.fillStyle = value
        context.clearRect(0, 0, 1, 1)
        context.fillRect(0, 0, 1, 1)

        const [red, green, blue, alpha] = context.getImageData(0, 0, 1, 1).data

        return alpha === 255
            ? `rgb(${red}, ${green}, ${blue})`
            : `rgba(${red}, ${green}, ${blue}, ${(alpha / 255).toFixed(3)})`
    }
})()

export default function dependencyGraph({ graph, selected, layout }) {
    return {
        cy: null,

        themeObserver: null,

        resizeObserver: null,

        centerListener: null,

        needsFit: false,

        init() {
            this.build()

            this.centerListener = (event) => {
                const nodeId = event?.detail?.nodeId

                if (nodeId) {
                    this.centerOn(nodeId)
                }
            }

            window.addEventListener('dependency-graph-center', this.centerListener)

            this.themeObserver = new MutationObserver(() => this.applyStyles())
            this.themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            })

            // The canvas resizes when the inspector opens or closes; without
            // this, Cytoscape keeps stale dimensions and hit-testing drifts.
            // It also finishes the initial fit when the component mounted
            // inside a container that had no dimensions yet.
            this.resizeObserver = new ResizeObserver(() => {
                this.cy?.resize()

                if (this.needsFit) {
                    this.fit()
                }
            })
            this.resizeObserver.observe(this.$refs.container)
        },

        destroy() {
            window.removeEventListener('dependency-graph-center', this.centerListener)
            this.themeObserver?.disconnect()
            this.resizeObserver?.disconnect()
            this.cy?.destroy()
        },

        isDark() {
            return document.documentElement.classList.contains('dark')
        },

        // The palette follows the Filament theme: each entry reads a theme
        // shade variable and falls back to the default theme color. The
        // variables are read from the graph container so scoped overrides
        // between :root and the canvas are honored too.
        palette() {
            const rootStyles = getComputedStyle(this.$refs.container ?? document.documentElement)

            const themeColor = (variable, fallback) => {
                const raw = rootStyles.getPropertyValue(variable).trim()

                return raw === '' ? fallback : normalizeColor(raw, fallback)
            }

            return this.isDark()
                ? {
                      text: themeColor('--gray-200', '#e5e7eb'),
                      subtitle: themeColor('--gray-400', '#9ca3af'),
                      edge: themeColor('--gray-600', '#4b5563'),
                      edgeLabel: themeColor('--gray-400', '#9ca3af'),
                      labelBackground: themeColor('--gray-800', '#1f2937'),
                      structuralEdge: themeColor('--gray-700', '#374151'),
                      panel: themeColor('--primary-950', '#312e81'),
                      panelBorder: themeColor('--primary-500', '#6366f1'),
                      resource: themeColor('--success-950', '#134e4a'),
                      resourceBorder: themeColor('--success-500', '#14b8a6'),
                      model: themeColor('--gray-800', '#1f2937'),
                      modelBorder: themeColor('--gray-500', '#6b7280'),
                      polymorphic: themeColor('--info-950', '#4a044e'),
                      polymorphicBorder: themeColor('--info-500', '#d946ef'),
                      selection: themeColor('--warning-500', '#f59e0b'),
                  }
                : {
                      text: themeColor('--gray-950', '#111827'),
                      subtitle: themeColor('--gray-500', '#6b7280'),
                      edge: themeColor('--gray-400', '#9ca3af'),
                      edgeLabel: themeColor('--gray-500', '#6b7280'),
                      labelBackground: themeColor('--gray-50', '#f9fafb'),
                      structuralEdge: themeColor('--gray-300', '#d1d5db'),
                      panel: themeColor('--primary-100', '#e0e7ff'),
                      panelBorder: themeColor('--primary-500', '#6366f1'),
                      resource: themeColor('--success-100', '#ccfbf1'),
                      resourceBorder: themeColor('--success-600', '#0d9488'),
                      model: themeColor('--gray-50', '#f9fafb'),
                      modelBorder: themeColor('--gray-400', '#9ca3af'),
                      polymorphic: themeColor('--info-100', '#fae8ff'),
                      polymorphicBorder: themeColor('--info-600', '#c026d3'),
                      selection: themeColor('--warning-600', '#d97706'),
                  }
        },

        styles() {
            const colors = this.palette()

            return [
                {
                    selector: 'node',
                    style: {
                        label: 'data(label)',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-size': '11px',
                        'min-zoomed-font-size': 5,
                        color: colors.text,
                        width: nodeWidth,
                        height: '36px',
                        padding: '12px',
                        shape: 'round-rectangle',
                        'background-color': colors.model,
                        'border-width': 1.5,
                        'border-color': colors.modelBorder,
                        'text-wrap': 'wrap',
                        'text-max-width': '160px',
                    },
                },
                {
                    selector: 'node[type = "panel"]',
                    style: {
                        shape: 'barrel',
                        padding: '16px',
                        'background-color': colors.panel,
                        'border-color': colors.panelBorder,
                        'font-weight': 'bold',
                    },
                },
                {
                    selector: 'node[type = "resource"]',
                    style: {
                        shape: 'hexagon',
                        padding: '16px',
                        'background-color': colors.resource,
                        'border-color': colors.resourceBorder,
                    },
                },
                {
                    selector: 'node[type = "polymorphic_target"]',
                    style: {
                        shape: 'diamond',
                        padding: '20px',
                        'background-color': colors.polymorphic,
                        'border-color': colors.polymorphicBorder,
                        'border-style': 'dashed',
                    },
                },
                {
                    selector: 'edge',
                    style: {
                        width: 1.5,
                        'line-color': colors.edge,
                        'target-arrow-color': colors.edge,
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier',
                        'arrow-scale': 0.8,
                    },
                },
                {
                    selector: 'edge[type != "model_relation"]',
                    style: {
                        'line-style': 'dashed',
                        'line-color': colors.structuralEdge,
                        'target-arrow-color': colors.structuralEdge,
                    },
                },
                {
                    selector: 'edge[type = "model_relation"]',
                    style: {
                        label: 'data(label)',
                        'font-size': '9px',
                        // Hide edge labels while zoomed out: at overview scale
                        // they are unreadable and only add visual noise.
                        'min-zoomed-font-size': 7,
                        color: colors.edgeLabel,
                        'text-rotation': 'autorotate',
                        'text-background-color': colors.labelBackground,
                        'text-background-opacity': 0.85,
                        'text-background-padding': '2px',
                        'text-background-shape': 'roundrectangle',
                        'text-margin-y': -6,
                    },
                },
                {
                    selector: '.fdg-selected',
                    style: {
                        'border-width': 3,
                        'border-color': colors.selection,
                        'line-color': colors.selection,
                        'target-arrow-color': colors.selection,
                    },
                },
                {
                    selector: '.fdg-faded',
                    style: {
                        opacity: 0.15,
                        'text-opacity': 0.4,
                    },
                },
            ]
        },

        layoutOptions() {
            if (layout === 'force') {
                return {
                    name: 'fcose',
                    quality: 'proof',
                    animate: false,
                    randomize: true,
                    padding: 30,
                    nodeRepulsion: 8000,
                    idealEdgeLength: 110,
                    packComponents: true,
                }
            }

            return {
                name: 'dagre',
                rankDir: 'TB',
                ranker: 'network-simplex',
                nodeSep: 28,
                edgeSep: 16,
                rankSep: 80,
                padding: 30,
            }
        },

        build() {
            const elements = [
                ...graph.nodes.map((node) => ({
                    data: {
                        id: node.id,
                        label: node.subtitle ? `${node.label}\n${node.subtitle}` : node.label,
                        type: node.type,
                    },
                })),
                ...graph.edges.map((edge) => ({
                    data: {
                        id: edge.id,
                        source: edge.source,
                        target: edge.target,
                        label: edge.label,
                        type: edge.type,
                    },
                })),
            ]

            this.cy = cytoscape({
                container: this.$refs.container,
                elements,
                style: this.styles(),
                wheelSensitivity: 0.2,
                minZoom: 0.1,
                maxZoom: 3,
            })

            // The layout runs after construction so the fit listener is in
            // place first: the built-in fit silently no-ops when the lazily
            // mounted container has not been measured yet.
            this.needsFit = true

            const initialLayout = this.cy.layout(this.layoutOptions())
            initialLayout.one('layoutstop', () => this.fit())
            initialLayout.run()

            this.cy.on('tap', 'node', (event) => {
                this.select(event.target.id())
                this.$wire.selectNode(event.target.id())
            })

            this.cy.on('tap', 'edge', (event) => {
                this.select(event.target.id())
                this.$wire.selectEdge(event.target.id())
            })

            this.cy.on('tap', (event) => {
                if (event.target === this.cy) {
                    this.cy.elements().removeClass('fdg-selected fdg-faded')
                    this.$wire.clearSelection()
                }
            })

            if (selected) {
                this.select(selected)
                this.centerOn(selected)
            }
        },

        select(elementId) {
            this.cy.elements().removeClass('fdg-selected fdg-faded')

            const element = this.cy.getElementById(elementId)

            if (element.empty()) {
                return
            }

            element.addClass('fdg-selected')

            // Focus + context: keep the selection and its direct neighborhood
            // at full opacity and fade everything else.
            const neighborhood = element.isNode()
                ? element.closedNeighborhood()
                : element.connectedNodes().union(element)

            this.cy.elements().not(neighborhood).addClass('fdg-faded')
        },

        centerOn(nodeId) {
            const node = this.cy?.getElementById(nodeId)

            if (node && node.nonempty()) {
                this.select(nodeId)
                this.cy.animate({ center: { eles: node }, zoom: 1.2, duration: 250 })
            }
        },

        fit() {
            if (! this.cy || this.cy.width() === 0 || this.cy.height() === 0) {
                return
            }

            this.needsFit = false
            this.cy.fit(undefined, 40)
        },

        applyStyles() {
            if (this.cy) {
                this.cy.style(this.styles())
            }
        },
    }
}
