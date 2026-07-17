<?php
/**
 * Viewport-Optimized Workflow Graph
 * Features SVG Canvas connections, 20px high-contrast typography, and raw HTML formatting.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="fop-dashboard-container">
	
	<div class="fop-graph-header">
		<h2><?php esc_html_e( 'Store Owner Journey Map', 'fop-plugin' ); ?></h2>
		<div class="fop-graph-legend">
			<div><span class="legend-dot bg-auto"></span><?php esc_html_e( 'Automation', 'fop-plugin' ); ?></div>
			<div><span class="legend-dot bg-manual"></span><?php esc_html_e( 'Decision / Operations', 'fop-plugin' ); ?></div>
			<div><span class="legend-dot bg-success"></span><?php esc_html_e( 'Success Track', 'fop-plugin' ); ?></div>
			<div><span class="legend-dot bg-danger"></span><?php esc_html_e( 'Blocked Track', 'fop-plugin' ); ?></div>
		</div>
	</div>

	<div class="fop-canvas-viewport">
		
		<svg id="fop-svg-layer"></svg>

		<div class="fop-grid-layout">
			
			<div class="fop-grid-row centering-row">
				<div id="node-root" class="fop-node bg-auto">
					<strong>Customer lands on Checkout page & fills Info</strong>
				</div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col">
					<div id="node-leaves" class="fop-node bg-manual">
						<strong>Leaves Website</strong>
					</div>
				</div>
				
				<div class="fop-col">
					<div id="node-click-order" class="fop-node bg-auto">
						<strong>Clicks Order Button</strong>
					</div>
				</div>
				<div class="fop-col"></div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col">
					<div id="node-incomplete" class="fop-node bg-auto">
						<strong>Incomplete Orders</strong>
						<span class="fop-badge" id="count-incomplete">...</span>
					</div>
				</div>
				<div class="fop-col">
					<div id="node-security" class="fop-node node-diamond bg-manual">
						<div class="diamond-content">
							<strong>Security Checks</strong>
							<ul class="custom-node-list">
								<li>Is Number/Email Valid?</li>
								<li>Is Blocked?</li>
								<li>Is Duplicate order?</li>
							</ul>
						</div>
					</div>
				</div>
				<div class="fop-col"></div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col">
					<div id="node-failed-alert" class="fop-node bg-danger">
						<strong>Failed: <br> Show Alert & Stop him</strong>
					</div>
				</div>
				<div class="fop-col">
					<div id="node-passed-placed" class="fop-node bg-success">
						<strong>Passed: Order Placed</strong>
						<span class="fop-badge" id="count-placed">...</span>
					</div>
				</div>
				<div class="fop-col"></div>
			</div>

			<div class="fop-grid-row centering-row">
				<div id="node-auto-lock" class="fop-node bg-auto">
					<strong>Auto Lock for Specified time to prevent Duplicate order</strong>
				</div>
			</div>

			<div class="fop-grid-row centering-row">
				<div id="node-courier-analysis" class="fop-node bg-auto">
					<strong>Courier Score Analysis</strong>
				</div>
			</div>

			<div class="fop-grid-row centering-row">
				<div id="node-score-check" class="fop-node node-diamond bg-manual">
					<div class="diamond-content">
						<strong>Score > 80%?</strong>
					</div>
				</div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col"></div>
				<div class="fop-col">
					<div id="node-above-80" class="fop-node bg-success">
						<strong>Above 80%</strong>
					</div>
				</div>
				<div class="fop-col">
					<div id="node-below-80" class="fop-node bg-manual">
						<strong>Below 80%: Manual Call</strong>
					</div>
				</div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col"></div>
				<div class="fop-col">
					<div id="node-processing" class="fop-node bg-success">
						<strong>Order Processing</strong>
						<span class="fop-badge" id="count-processing">...</span>
					</div>
				</div>
				<div class="fop-col">
					<div class="dual-flex-nodes">
						<div id="node-authentic" class="fop-node bg-success">
							<strong>Authentic</strong>
						</div>
						<div id="node-fraud-block" class="fop-node bg-danger">
							<strong>Fraud: Block</strong>
						</div>
					</div>
				</div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col">
					<div id="node-parcel-received" class="fop-node bg-success">
						<strong>Parcel Received</strong>
						<span class="fop-badge" id="count-received">...</span>
					</div>
				</div>
				<div class="fop-col">
					<div id="node-not-received" class="fop-node bg-manual">
						<strong>Not Received</strong>
						<span class="fop-badge" id="count-not-received">...</span>
					</div>
				</div>
				<div class="fop-col"></div>
			</div>

			<div class="fop-grid-row triple-col-row">
				<div class="fop-col"></div>
				<div class="fop-col col-span-2">
					<div class="fault-breakdown-row">
						<div id="node-fault-customer" class="fop-node bg-manual">
							<strong>Customer Fault</strong>
							<div class="fop-mini-desc">Hold Time</div>
						</div>
						<div id="node-fault-courier" class="fop-node bg-manual">
							<strong>Courier Fault</strong>
							<div class="fop-mini-desc">Review Carrier</div>
						</div>
						<div id="node-fault-shop" class="fop-node bg-manual">
							<strong>Shop Fault</strong>
							<div class="fop-mini-desc">Fix Operations</div>
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>

<style>
	/* =========================================================
		1. Global Core Configuration (20px Base Layout)
		========================================================= */
	:root {
		--color-auto: #2563eb;
		--color-manual: #d97706;
		--color-success: #059669;
		--color-danger: #dc2626;
		--line-color: #cbd5e1;
		--line-thickness: 3px;
	}

	.fop-dashboard-container {
		margin: 20px 20px 20px 0;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	}

	/* =========================================================
		2. Viewport Optimization Styles
		========================================================= */
	.fop-canvas-viewport {
		position: relative;
		width: 100%;
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 16px;
		padding: 60px 20px;
		box-sizing: border-box;
	}

	#fop-svg-layer {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		pointer-events: none;
		z-index: 1;
	}

	.fop-grid-layout {
		position: relative;
		z-index: 2;
		display: flex;
		flex-direction: column;
		gap: 65px; /* Perfect space matrix for clean line sweeps */
		max-width: 1400px;
		margin: 0 auto;
	}

	/* Flex Columns Grid Rules */
	.fop-grid-row {
		display: flex;
		align-items: center;
		width: 100%;
	}
	.centering-row { justify-content: center; }
	.triple-col-row { justify-content: space-between; }
	
	.fop-col {
		flex: 1;
		display: flex;
		justify-content: center;
		align-items: center;
		min-width: 0;
	}
	.col-span-2 { flex: 2; }

	/* =========================================================
		3. Strict 20px Node UI Elements
		========================================================= */
	.fop-node {
		position: relative;
		padding: 16px 28px;
		border-radius: 50px;
		font-size: 20px; /* Primary request */
		font-weight: 600;
		text-align: center;
		line-height: 1.4;
		box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
		color: #ffffff;
		max-width: 360px;
		box-sizing: border-box;
	}

	/* Diamond Flowchart Override Layout */
	.fop-node.node-diamond {
		border-radius: 12px;
		width: 260px;
		height: 260px;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 20px;
		transform: rotate(45deg);
	}
	
	.diamond-content {
		transform: rotate(-45deg);
		width: 280px;
		text-align: center;
	}

	/* Sub-text styles within unnested HTML containers */
	.custom-node-list {
		margin: 8px 0 0 0;
		padding-left: 20px;
		font-size: 15px;
		font-weight: 500;
		text-align: left;
		opacity: 0.9;
		line-height: 1.3;
	}
	.fop-mini-desc {
		font-size: 15px;
		font-weight: 400;
		opacity: 0.85;
		margin-top: 2px;
	}

	/* Helper Element Track Wrappers */
	.dual-flex-nodes, .fault-breakdown-row {
		display: flex;
		gap: 20px;
		justify-content: center;
		width: 100%;
	}

	/* Brand Accent Colors */
	.bg-auto { background: var(--color-auto); }
	.bg-manual { background: var(--color-manual); }
	.bg-success { background: var(--color-success); }
	.bg-danger { background: var(--color-danger); }

	/* UI Badge Component Counters */
	.fop-badge {
		position: absolute;
		top: -12px;
		right: 10px;
		background: #0f172a;
		color: #fff;
		font-size: 13px;
		font-weight: bold;
		padding: 4px 10px;
		border-radius: 20px;
		border: 2px solid #ffffff;
	}

	/* Header Components */
	.fop-graph-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
	.fop-graph-header h2 { font-size: 24px; margin: 0; }
	.fop-graph-legend { display: flex; gap: 20px; font-size: 15px; font-weight: 600; }
	.legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const svg = document.getElementById('fop-svg-layer');

	function updateWorkflowLines() {
		// Reset old lines completely
		svg.innerHTML = '';
		const viewRect = svg.getBoundingClientRect();

		// Line-builder script utilities
		function getCoords(id, edge = 'bottom') {
			const el = document.getElementById(id);
			if (!el) return null;
			const r = el.getBoundingClientRect();
			
			let x = r.left + (r.width / 2) - viewRect.left;
			let y = r.top - viewRect.top;
			
			if (edge === 'bottom') y += r.height;
			if (edge === 'left') { x = r.left - viewRect.left; y += (r.height / 2); }
			if (edge === 'right') { x = r.right - viewRect.left; y += (r.height / 2); }
			return { x, y };
		}

		function createPath(d, color = 'var(--line-color)', arrow = false) {
			const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
			p.setAttribute('d', d);
			p.setAttribute('stroke', color);
			p.setAttribute('stroke-width', 'var(--line-thickness)');
			p.setAttribute('fill', 'none');
			p.setAttribute('stroke-linecap', 'round');
			svg.appendChild(p);
		}

		// 1. Draw The Funnel Entrance Curves above the Root Node
		const rootTop = getCoords('node-root', 'top');
		if (rootTop) {
			const leftFunnelX = rootTop.x - 120;
			const rightFunnelX = rootTop.x + 120;
			const funnelTopY = rootTop.y - 45;
			
			// Left curve and right curve flowing inward cleanly
			createPath(`M ${leftFunnelX} ${funnelTopY} Q ${leftFunnelX + 60} ${rootTop.y} ${rootTop.x} ${rootTop.y}`);
			createPath(`M ${rightFunnelX} ${funnelTopY} Q ${rightFunnelX - 60} ${rootTop.y} ${rootTop.x} ${rootTop.y}`);
		}

		// Standard Connection Calculator (S-Curve)
		function link(fromId, toId, fromEdge='bottom', toEdge='top', color='var(--line-color)') {
			const start = getCoords(fromId, fromEdge);
			const end = getCoords(toId, toEdge);
			if (!start || !end) return;

			let d;
			if (fromEdge === 'bottom' && toEdge === 'top') {
				const midY = start.y + (end.y - start.y) / 2;
				d = `M ${start.x} ${start.y} C ${start.x} ${midY}, ${end.x} ${midY}, ${end.x} ${end.y}`;
			} else if (fromEdge === 'right' && toEdge === 'left') {
				const midX = start.x + (end.x - start.x) / 2;
				d = `M ${start.x} ${start.y} C ${midX} ${start.y}, ${midX} ${end.y}, ${end.x} ${end.y}`;
			} else {
				// Flat line fallbacks
				d = `M ${start.x} ${start.y} L ${end.x} ${end.y}`;
			}
			createPath(d, color);
		}

		// 2. Map Hierarchy Traces
		link('node-root', 'node-leaves');
		link('node-root', 'node-click-order');
		link('node-leaves', 'node-incomplete');
		link('node-click-order', 'node-security');
		
		// 3. Connection: Failed Alert loops over cleanly to Incomplete Orders
		link('node-security', 'node-failed-alert');
		link('node-failed-alert', 'node-incomplete', 'left', 'right', 'var(--color-danger)');

		// 4. Main Validation flow pathing
		link('node-security', 'node-passed-placed');
		link('node-passed-placed', 'node-auto-lock');
		link('node-auto-lock', 'node-courier-analysis');
		link('node-courier-analysis', 'node-score-check');

		// 5. Score split tracking
		link('node-score-check', 'node-above-80');
		link('node-score-check', 'node-below-80');
		link('node-above-80', 'node-processing');
		link('node-below-80', 'node-authentic');
		link('node-below-80', 'node-fraud-block', 'bottom', 'top', 'var(--color-danger)');

		// 6. Cross-Branch Loopback Connection: Authentic loops right back into Order Processing
		link('node-authentic', 'node-processing', 'left', 'right', 'var(--color-success)');

		// 7. Base logistics fulfillment mappings
		link('node-processing', 'node-parcel-received');
		link('node-processing', 'node-not-received');
		
		link('node-not-received', 'node-fault-customer');
		link('node-not-received', 'node-fault-courier');
		link('node-not-received', 'node-fault-shop');
	}

	// Dynamic execution triggers
	window.addEventListener('resize', updateWorkflowLines);
	setTimeout(updateWorkflowLines, 150); // Ensures elements are painted for correct bounds calculation

	// Sample AJAX counter injector simulation
	setTimeout(function() {
		const liveCounts = {
			'count-incomplete': 24,
			'count-placed': 412,
			'count-processing': 18,
			'count-received': 389,
			'count-not-received': 5
		};
		for (let id in liveCounts) {
			const target = document.getElementById(id);
			if (target) target.innerText = liveCounts[id];
		}
	}, 450);
});
</script>
