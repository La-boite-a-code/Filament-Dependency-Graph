import cytoscape from 'cytoscape'

export default function dependencyGraph({ graph, selected, layout }) {
    return {
        cy: null,

        themeObserver: null,

        centerListener: null,

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
        },

        destroy() {
            window.removeEventListener('dependency-graph-center', this.centerListener)
            this.themeObserver?.disconnect()
            this.cy?.destroy()
        },

        isDark() {
            return document.documentElement.classList.contains('dark')
        },

        palette() {
            return this.isDark()
                ? {
                      text: '#e5e7eb',
                      subtitle: '#9ca3af',
                      edge: '#4b5563',
                      edgeLabel: '#9ca3af',
                      structuralEdge: '#374151',
                      panel: '#312e81',
                      panelBorder: '#6366f1',
                      resource: '#134e4a',
                      resourceBorder: '#14b8a6',
                      model: '#1f2937',
                      modelBorder: '#6b7280',
                      polymorphic: '#4a044e',
                      polymorphicBorder: '#d946ef',
                      selection: '#f59e0b',
                  }
                : {
                      text: '#111827',
                      subtitle: '#6b7280',
                      edge: '#9ca3af',
                      edgeLabel: '#6b7280',
                      structuralEdge: '#d1d5db',
                      panel: '#e0e7ff',
                      panelBorder: '#6366f1',
                      resource: '#ccfbf1',
                      resourceBorder: '#0d9488',
                      model: '#f9fafb',
                      modelBorder: '#9ca3af',
                      polymorphic: '#fae8ff',
                      polymorphicBorder: '#c026d3',
                      selection: '#d97706',
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
                        color: colors.text,
                        width: 'label',
                        height: '34px',
                        padding: '10px',
                        shape: 'round-rectangle',
                        'background-color': colors.model,
                        'border-width': 1.5,
                        'border-color': colors.modelBorder,
                        'text-wrap': 'wrap',
                        'text-max-width': '140px',
                    },
                },
                {
                    selector: 'node[type = "panel"]',
                    style: {
                        shape: 'barrel',
                        'background-color': colors.panel,
                        'border-color': colors.panelBorder,
                        'font-weight': 'bold',
                    },
                },
                {
                    selector: 'node[type = "resource"]',
                    style: {
                        shape: 'hexagon',
                        'background-color': colors.resource,
                        'border-color': colors.resourceBorder,
                    },
                },
                {
                    selector: 'node[type = "polymorphic_target"]',
                    style: {
                        shape: 'diamond',
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
                        color: colors.edgeLabel,
                        'text-rotation': 'autorotate',
                        'text-background-opacity': 0,
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
            ]
        },

        layoutOptions() {
            if (layout === 'force') {
                return {
                    name: 'cose',
                    animate: false,
                    padding: 30,
                    nodeOverlap: 12,
                }
            }

            const roots = graph.nodes
                .filter((node) => node.type === 'panel')
                .map((node) => node.id)

            return {
                name: 'breadthfirst',
                directed: true,
                padding: 30,
                spacingFactor: 1.15,
                roots: roots.length > 0 ? roots : undefined,
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
                layout: this.layoutOptions(),
                wheelSensitivity: 0.2,
                minZoom: 0.1,
                maxZoom: 3,
            })

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
                    this.cy.elements().removeClass('fdg-selected')
                    this.$wire.clearSelection()
                }
            })

            if (selected) {
                this.select(selected)
                this.centerOn(selected)
            }
        },

        select(elementId) {
            this.cy.elements().removeClass('fdg-selected')

            const element = this.cy.getElementById(elementId)

            if (element.nonempty()) {
                element.addClass('fdg-selected')
            }
        },

        centerOn(nodeId) {
            const node = this.cy?.getElementById(nodeId)

            if (node && node.nonempty()) {
                this.select(nodeId)
                this.cy.animate({ center: { eles: node }, zoom: 1.2, duration: 250 })
            }
        },

        fit() {
            this.cy?.fit(undefined, 40)
        },

        applyStyles() {
            if (this.cy) {
                this.cy.style(this.styles())
            }
        },
    }
}
