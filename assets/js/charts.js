/**
 * ============================================================
 * MedWell Pharmacy - Charts Initialization JavaScript
 * All charts use the avocado green palette
 * ============================================================
 */

'use strict';

const MedWellCharts = (function () {

  /* ----------------------------------------------------------
     Avocado Green Color Palette
     ---------------------------------------------------------- */
  const palette = {
    primary:        '#7CB342',
    primaryLight:   '#9CCC65',
    primaryDark:    '#558B2F',
    primary50:      '#f1f8e9',
    primary100:     '#dcedc8',
    primary200:     '#c5e1a5',
    accent:         '#33691E',
    success:        '#27ae60',
    danger:         '#e74c3c',
    warning:        '#f39c12',
    info:           '#3498db',
    muted:          '#b2bec3',
    secondary:      '#636e72',
    darkGreen:      '#2E7D32',
    lightGreen:     '#AED581',
    lime:           '#C0CA33',
    teal:           '#009688',
    olive:          '#827717',
};

  const chartColors = [
    palette.primary,
    palette.primaryLight,
    palette.primaryDark,
    palette.success,
    palette.warning,
    palette.info,
    palette.teal,
    palette.lime,
    palette.olive,
    palette.accent,
    palette.danger,
    palette.lightGreen,
  ];

  const chartColorsBg = [
    'rgba(124, 179, 66, 0.15)',
    'rgba(156, 204, 101, 0.15)',
    'rgba(85, 139, 47, 0.15)',
    'rgba(39, 174, 96, 0.15)',
    'rgba(243, 156, 18, 0.15)',
    'rgba(52, 152, 219, 0.15)',
    'rgba(0, 150, 136, 0.15)',
    'rgba(192, 202, 51, 0.15)',
    'rgba(130, 119, 23, 0.15)',
    'rgba(51, 105, 30, 0.15)',
    'rgba(231, 76, 60, 0.15)',
    'rgba(174, 213, 129, 0.15)',
  ];

  /* ----------------------------------------------------------
     Chart Instances Storage
     ---------------------------------------------------------- */
  const charts = {};

  /* ----------------------------------------------------------
     Common Chart Options
     ---------------------------------------------------------- */
  function getCommonOptions(extra = {}) {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
    const tickColor = isDark ? '#9aa0a6' : '#636e72';

    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            font: { family: "'Inter', sans-serif", size: 12 },
            color: tickColor,
            padding: 16,
            usePointStyle: true,
            pointStyle: 'circle',
          },
        },
        tooltip: {
          backgroundColor: isDark ? 'rgba(35, 40, 56, 0.95)' : 'rgba(0,0,0,0.85)',
          titleFont: { family: "'Inter', sans-serif", size: 13, weight: '600' },
          bodyFont: { family: "'Inter', sans-serif", size: 12 },
          padding: 12,
          cornerRadius: 8,
          displayColors: true,
          boxPadding: 4,
        },
      },
      scales: {
        x: {
          grid: { color: gridColor, drawBorder: false },
          ticks: { font: { family: "'Inter', sans-serif", size: 11 }, color: tickColor },
        },
        y: {
          grid: { color: gridColor, drawBorder: false },
          ticks: { font: { family: "'Inter', sans-serif", size: 11 }, color: tickColor },
          beginAtZero: true,
        },
      },
      ...extra,
    };
  }

  function getNoScalesOptions(extra = {}) {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const tickColor = isDark ? '#9aa0a6' : '#636e72';

    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { family: "'Inter', sans-serif", size: 12 },
            color: tickColor,
            padding: 16,
            usePointStyle: true,
            pointStyle: 'circle',
          },
        },
        tooltip: {
          backgroundColor: isDark ? 'rgba(35, 40, 56, 0.95)' : 'rgba(0,0,0,0.85)',
          titleFont: { family: "'Inter', sans-serif", size: 13, weight: '600' },
          bodyFont: { family: "'Inter', sans-serif", size: 12 },
          padding: 12,
          cornerRadius: 8,
        },
      },
      ...extra,
    };
  }

  /* ----------------------------------------------------------
     Sales Overview Chart (Line Chart)
     Supports daily / weekly / monthly toggle
     ---------------------------------------------------------- */
  function initSalesOverviewChart(canvasId, period = 'daily') {
    return loadChartData('/charts/sales-overview', { period }, (data) => {
      if (charts.salesOverview) {
        charts.salesOverview.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
      gradient.addColorStop(0, 'rgba(124, 179, 66, 0.25)');
      gradient.addColorStop(1, 'rgba(124, 179, 66, 0.01)');

      charts.salesOverview = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels || [],
          datasets: [
            {
              label: 'Sales',
              data: data.sales || [],
              borderColor: palette.primary,
              backgroundColor: gradient,
              borderWidth: 2.5,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: palette.primary,
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 7,
            },
            {
              label: 'Orders',
              data: data.orders || [],
              borderColor: palette.primaryLight,
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [5, 5],
              tension: 0.4,
              pointBackgroundColor: palette.primaryLight,
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 3,
              pointHoverRadius: 6,
            },
          ],
        },
        options: getCommonOptions({
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.dataset.label + ': ' + MedWell.formatCurrency(context.parsed.y);
                },
              },
            },
          },
        }),
      });

      return charts.salesOverview;
    });
  }

  function updateSalesOverviewPeriod(canvasId, period) {
    initSalesOverviewChart(canvasId, period);
  }

  /* ----------------------------------------------------------
     Revenue Chart (Bar Chart)
     ---------------------------------------------------------- */
  function initRevenueChart(canvasId) {
    return loadChartData('/charts/revenue', {}, (data) => {
      if (charts.revenue) {
        charts.revenue.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      charts.revenue = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.labels || [],
          datasets: [
            {
              label: 'Revenue',
              data: data.revenue || [],
              backgroundColor: chartColors[0],
              borderColor: palette.primaryDark,
              borderWidth: 1,
              borderRadius: 6,
              borderSkipped: false,
              barPercentage: 0.6,
              categoryPercentage: 0.7,
            },
            {
              label: 'Expenses',
              data: data.expenses || [],
              backgroundColor: chartColorsBg[4],
              borderColor: palette.warning,
              borderWidth: 1,
              borderRadius: 6,
              borderSkipped: false,
              barPercentage: 0.6,
              categoryPercentage: 0.7,
            },
          ],
        },
        options: getCommonOptions({
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.dataset.label + ': ' + MedWell.formatCurrency(context.parsed.y);
                },
              },
            },
          },
        }),
      });

      return charts.revenue;
    });
  }

  /* ----------------------------------------------------------
     Top Medicines (Horizontal Bar)
     ---------------------------------------------------------- */
  function initTopMedicinesChart(canvasId, limit = 10) {
    return loadChartData('/charts/top-medicines', { limit }, (data) => {
      if (charts.topMedicines) {
        charts.topMedicines.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      const bgColors = (data.medicines || []).map((_, i) => chartColors[i % chartColors.length]);
      const borderColors = (data.medicines || []).map((_, i) => chartColors[i % chartColors.length]);

      charts.topMedicines = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: data.labels || [],
          datasets: [{
            label: 'Units Sold',
            data: data.values || [],
            backgroundColor: bgColors.map(c => c + '33'),
            borderColor: borderColors,
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false,
          }],
        },
        options: getCommonOptions({
          indexAxis: 'y',
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.parsed.x + ' units sold';
                },
              },
            },
          },
          scales: {
            x: {
              grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
              ticks: { font: { family: "'Inter', sans-serif", size: 11 } },
              beginAtZero: true,
            },
            y: {
              grid: { display: false },
              ticks: { font: { family: "'Inter', sans-serif", size: 11 } },
            },
          },
        }),
      });

      return charts.topMedicines;
    });
  }

  /* ----------------------------------------------------------
     Payment Methods (Doughnut Chart)
     ---------------------------------------------------------- */
  function initPaymentMethodsChart(canvasId) {
    return loadChartData('/charts/payment-methods', {}, (data) => {
      if (charts.paymentMethods) {
        charts.paymentMethods.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      charts.paymentMethods = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: data.labels || ['Cash', 'Card', 'GCash', 'Bank Transfer'],
          datasets: [{
            data: data.values || [0, 0, 0, 0],
            backgroundColor: [
              palette.primary,
              palette.primaryLight,
              palette.info,
              palette.warning,
            ],
            borderColor: 'transparent',
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: getNoScalesOptions({
          cutout: '68%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 16,
                usePointStyle: true,
                pointStyle: 'circle',
              },
            },
            tooltip: {
              callbacks: {
                label: function (context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                  return context.label + ': ' + MedWell.formatCurrency(context.parsed) + ' (' + pct + '%)';
                },
              },
            },
          },
        }),
      });

      return charts.paymentMethods;
    });
  }

  /* ----------------------------------------------------------
     Category Distribution (Pie Chart)
     ---------------------------------------------------------- */
  function initCategoryDistributionChart(canvasId) {
    return loadChartData('/charts/category-distribution', {}, (data) => {
      if (charts.categoryDistribution) {
        charts.categoryDistribution.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      charts.categoryDistribution = new Chart(ctx, {
        type: 'pie',
        data: {
          labels: data.labels || [],
          datasets: [{
            data: data.values || [],
            backgroundColor: chartColors.slice(0, (data.labels || []).length),
            borderColor: 'transparent',
            borderWidth: 0,
            hoverOffset: 6,
          }],
        },
        options: getNoScalesOptions({
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                  return context.label + ': ' + pct + '%';
                },
              },
            },
          },
        }),
      });

      return charts.categoryDistribution;
    });
  }

  /* ----------------------------------------------------------
     Stock Status (Doughnut)
     ---------------------------------------------------------- */
  function initStockStatusChart(canvasId) {
    return loadChartData('/charts/stock-status', {}, (data) => {
      if (charts.stockStatus) {
        charts.stockStatus.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      charts.stockStatus = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['In Stock', 'Low Stock', 'Out of Stock', 'Expiring Soon'],
          datasets: [{
            data: data.values || [0, 0, 0, 0],
            backgroundColor: [
              palette.success,
              palette.warning,
              palette.danger,
              palette.info,
            ],
            borderColor: 'transparent',
            borderWidth: 0,
            hoverOffset: 8,
          }],
        },
        options: getNoScalesOptions({
          cutout: '65%',
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.label + ': ' + context.parsed + ' items';
                },
              },
            },
          },
        }),
      });

      return charts.stockStatus;
    });
  }

  /* ----------------------------------------------------------
     Profit Trend (Area Chart)
     ---------------------------------------------------------- */
  function initProfitTrendChart(canvasId) {
    return loadChartData('/charts/profit-trend', {}, (data) => {
      if (charts.profitTrend) {
        charts.profitTrend.destroy();
      }

      const ctx = document.getElementById(canvasId);
      if (!ctx) return null;

      const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
      gradient.addColorStop(0, 'rgba(85, 139, 47, 0.3)');
      gradient.addColorStop(1, 'rgba(85, 139, 47, 0.01)');

      charts.profitTrend = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels || [],
          datasets: [
            {
              label: 'Profit',
              data: data.profit || [],
              borderColor: palette.primaryDark,
              backgroundColor: gradient,
              borderWidth: 2.5,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: palette.primaryDark,
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 3,
              pointHoverRadius: 6,
            },
            {
              label: 'Revenue',
              data: data.revenue || [],
              borderColor: palette.primaryLight,
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [4, 4],
              tension: 0.4,
              pointRadius: 0,
              pointHoverRadius: 5,
            },
          ],
        },
        options: getCommonOptions({
          plugins: {
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.dataset.label + ': ' + MedWell.formatCurrency(context.parsed.y);
                },
              },
            },
          },
        }),
      });

      return charts.profitTrend;
    });
  }

  /* ----------------------------------------------------------
     Chart Data Loading via AJAX
     ---------------------------------------------------------- */
  function loadChartData(endpoint, params, callback) {
    // Attempt to load real data from the backend
    MedWell.ajax(endpoint, params, (err, result) => {
      if (err || !result) {
        // Use demo data if backend is unavailable
        const demoData = getDemoData(endpoint);
        callback(demoData);
        return;
      }
      callback(result.data || result);
    }, 'GET');

    // Return null synchronously; chart is created asynchronously in callback
    return null;
  }

  function getDemoData(endpoint) {
    const demoSets = {
      '/charts/sales-overview': {
        daily: {
          labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
          sales: [4200, 5100, 3800, 6200, 5700, 8100, 6800],
          orders: [32, 41, 28, 48, 44, 62, 53],
        },
        weekly: {
          labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
          sales: [28000, 32000, 29500, 35000],
          orders: [210, 245, 225, 270],
        },
        monthly: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
          sales: [120000, 115000, 132000, 128000, 140000, 152000, 145000, 160000, 155000, 168000, 175000, 190000],
          orders: [920, 880, 1010, 975, 1070, 1160, 1110, 1225, 1185, 1285, 1340, 1455],
        },
      },
      '/charts/revenue': {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        revenue: [120000, 115000, 132000, 128000, 140000, 152000],
        expenses: [78000, 75000, 82000, 80000, 88000, 95000],
      },
      '/charts/top-medicines': {
        labels: ['Paracetamol 500mg', 'Amoxicillin 250mg', 'Omeprazole 20mg', 'Cetirizine 10mg', 'Metformin 500mg', 'Amlodipine 5mg', 'Losartan 50mg', 'Ibuprofen 400mg'],
        values: [245, 198, 176, 152, 143, 128, 115, 98],
      },
      '/charts/payment-methods': {
        labels: ['Cash', 'Card', 'GCash', 'Bank Transfer'],
        values: [45000, 32000, 18500, 12500],
      },
      '/charts/category-distribution': {
        labels: ['Analgesics', 'Antibiotics', 'Antihypertensives', 'Antidiabetics', 'Antihistamines', 'Vitamins', 'Gastrointestinal'],
        values: [28, 22, 18, 14, 10, 5, 3],
      },
      '/charts/stock-status': {
        values: [420, 85, 32, 18],
      },
      '/charts/profit-trend': {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        profit: [42000, 40000, 50000, 48000, 52000, 57000, 54000, 60000, 58000, 63000, 66000, 72000],
        revenue: [120000, 115000, 132000, 128000, 140000, 152000, 145000, 160000, 155000, 168000, 175000, 190000],
      },
    };

    const data = demoSets[endpoint];
    if (endpoint === '/charts/sales-overview') {
      return data['daily'] || data;
    }
    return data || { labels: [], values: [] };
  }

  /* ----------------------------------------------------------
     Responsive Chart Resizing
     ---------------------------------------------------------- */
  function initResponsiveResize() {
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        Object.values(charts).forEach(chart => {
          if (chart && typeof chart.resize === 'function') {
            chart.resize();
          }
        });
      }, 200);
    });
  }

  /* ----------------------------------------------------------
     Chart Export Functionality
     ---------------------------------------------------------- */
  function exportChart(chartKey, filename = 'chart') {
    const chart = charts[chartKey];
    if (!chart) {
      MedWell.showToast('Chart not found for export.', 'warning');
      return;
    }

    try {
      const canvas = chart.canvas;
      const link = document.createElement('a');
      link.download = filename + '.png';
      link.href = canvas.toDataURL('image/png', 1.0);
      link.click();
      MedWell.showToast('Chart exported as ' + filename + '.png', 'success');
    } catch (e) {
      MedWell.showToast('Failed to export chart.', 'danger');
      console.error('Chart export error:', e);
    }
  }

  function exportAllCharts() {
    const keys = Object.keys(charts);
    keys.forEach((key, index) => {
      setTimeout(() => {
        exportChart(key, 'medwell-' + key);
      }, index * 300);
    });
  }

  /* ----------------------------------------------------------
     Destroy All Charts (for cleanup / theme change)
     ---------------------------------------------------------- */
  function destroyAllCharts() {
    Object.keys(charts).forEach(key => {
      if (charts[key] && typeof charts[key].destroy === 'function') {
        charts[key].destroy();
      }
      delete charts[key];
    });
  }

  /* ----------------------------------------------------------
     Refresh All Charts (reload data)
     ---------------------------------------------------------- */
  function refreshAllCharts() {
    // Re-initialize all known charts
    const initFunctions = [
      { fn: () => initSalesOverviewChart('sales-overview-chart'), key: 'salesOverview' },
      { fn: () => initRevenueChart('revenue-chart'), key: 'revenue' },
      { fn: () => initTopMedicinesChart('top-medicines-chart'), key: 'topMedicines' },
      { fn: () => initPaymentMethodsChart('payment-methods-chart'), key: 'paymentMethods' },
      { fn: () => initCategoryDistributionChart('category-chart'), key: 'categoryDistribution' },
      { fn: () => initStockStatusChart('stock-status-chart'), key: 'stockStatus' },
      { fn: () => initProfitTrendChart('profit-trend-chart'), key: 'profitTrend' },
    ];

    initFunctions.forEach(item => {
      const canvas = document.getElementById(item.key.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/^-/, '') + '-chart') ||
                     document.getElementById(Object.keys({ salesOverview: 'sales-overview-chart', revenue: 'revenue-chart', topMedicines: 'top-medicines-chart', paymentMethods: 'payment-methods-chart', categoryDistribution: 'category-chart', stockStatus: 'stock-status-chart', profitTrend: 'profit-trend-chart' })[item.key]);
      if (canvas) {
        item.fn();
      }
    });
  }

  /* ----------------------------------------------------------
     Theme Change Handler
     Rebuilds charts with correct colors for dark/light mode
     ---------------------------------------------------------- */
  function handleThemeChange() {
    // Destroy existing charts and re-init
    const activeChartIds = Object.keys(charts);
    destroyAllCharts();

    // Re-initialize charts that were active
    activeChartIds.forEach(key => {
      const canvasIdMap = {
        salesOverview: 'sales-overview-chart',
        revenue: 'revenue-chart',
        topMedicines: 'top-medicines-chart',
        paymentMethods: 'payment-methods-chart',
        categoryDistribution: 'category-chart',
        stockStatus: 'stock-status-chart',
        profitTrend: 'profit-trend-chart',
      };

      const canvasId = canvasIdMap[key];
      if (canvasId && document.getElementById(canvasId)) {
        switch (key) {
          case 'salesOverview': initSalesOverviewChart(canvasId); break;
          case 'revenue': initRevenueChart(canvasId); break;
          case 'topMedicines': initTopMedicinesChart(canvasId); break;
          case 'paymentMethods': initPaymentMethodsChart(canvasId); break;
          case 'categoryDistribution': initCategoryDistributionChart(canvasId); break;
          case 'stockStatus': initStockStatusChart(canvasId); break;
          case 'profitTrend': initProfitTrendChart(canvasId); break;
        }
      }
    });
  }

  // Listen for theme changes
  document.addEventListener('medwell:themechange', handleThemeChange);

  /* ----------------------------------------------------------
     Auto-init Charts on DOMContentLoaded
     Finds all chart canvases with data attributes and inits them
     ---------------------------------------------------------- */
  function autoInitCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach(canvas => {
      const chartType = canvas.dataset.chart;
      const canvasId = canvas.id;

      switch (chartType) {
        case 'sales-overview':
          initSalesOverviewChart(canvasId, canvas.dataset.period || 'daily');
          break;
        case 'revenue':
          initRevenueChart(canvasId);
          break;
        case 'top-medicines':
          initTopMedicinesChart(canvasId, parseInt(canvas.dataset.limit) || 10);
          break;
        case 'payment-methods':
          initPaymentMethodsChart(canvasId);
          break;
        case 'category-distribution':
          initCategoryDistributionChart(canvasId);
          break;
        case 'stock-status':
          initStockStatusChart(canvasId);
          break;
        case 'profit-trend':
          initProfitTrendChart(canvasId);
          break;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initResponsiveResize();
    autoInitCharts();
  });

  /* ----------------------------------------------------------
     Public API
     ---------------------------------------------------------- */
  return {
    // Palette
    palette,
    chartColors,
    chartColorsBg,
    // Chart initializers
    initSalesOverviewChart,
    updateSalesOverviewPeriod,
    initRevenueChart,
    initTopMedicinesChart,
    initPaymentMethodsChart,
    initCategoryDistributionChart,
    initStockStatusChart,
    initProfitTrendChart,
    // Chart management
    charts,
    destroyAllCharts,
    refreshAllCharts,
    handleThemeChange,
    // Export
    exportChart,
    exportAllCharts,
    // Utilities
    getCommonOptions,
    getNoScalesOptions,
    // Auto-init
    autoInitCharts,
  };

})();
