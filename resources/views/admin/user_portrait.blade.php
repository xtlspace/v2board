<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title>{{$title}} - 用户画像</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="/assets/admin/vendor/echarts.min.js?v={{$version}}"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f0f2f5; color: #333; }
        a { text-decoration: none; color: inherit; }

        .header { height: 64px; background: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 50; }
        .header h1 { font-size: 18px; font-weight: 600; color: #333; }

        .content { padding: 24px; max-width: 1400px; margin: 0 auto; }

        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 24px; }
        .card-body { padding: 20px 24px; }

        .tabs { display: flex; gap: 0; border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; font-size: 14px; cursor: pointer; border: none; background: none; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn:hover { color: #1890ff; }
        .tab-btn.active { color: #1890ff; border-bottom-color: #1890ff; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 12px; color: #888; font-weight: 500; }
        .filter-group input,
        .filter-group select { padding: 6px 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; min-width: 140px; outline: none; transition: border-color 0.2s; background: #fff; }
        .filter-group input:focus,
        .filter-group select:focus { border-color: #1890ff; box-shadow: 0 0 0 2px rgba(24,144,255,0.2); }
        .filter-group input { min-width: 120px; }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: -4px 0 14px; }
        .filter-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border: 1px solid #91d5ff; border-radius: 14px; background: #e6f7ff; color: #096dd9; font-size: 12px; }
        .filter-chip .remove { cursor: pointer; font-weight: 700; line-height: 1; }
        .filter-chip .remove:hover { color: #ff4d4f; }
        .filter-chip.clear-all { border-color: #d9d9d9; background: #fff; color: #666; cursor: pointer; }
        .quick-filter { color: #1890ff; cursor: pointer; }
        .quick-filter:hover { text-decoration: underline; }
        .btn { padding: 7px 16px; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #1890ff; color: #fff; }
        .btn-primary:hover { background: #40a9ff; }
        .btn-danger { background: #ff4d4f; color: #fff; }
        .btn-danger:hover { background: #ff7875; }
        .btn-success { background: #52c41a; color: #fff; }
        .btn-success:hover { background: #73d13d; }
        .btn-default { background: #fff; color: #333; border: 1px solid #d9d9d9; }
        .btn-default:hover { color: #1890ff; border-color: #1890ff; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th { background: #fafafa; font-weight: 600; color: #555; text-align: left; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
        table th.sortable { cursor: pointer; user-select: none; }
        table th.sortable:hover { color: #1890ff; }
        table th i.fa-sort { margin-left: 4px; opacity: 0.3; font-size: 11px; }
        table th.active i.fa-sort { opacity: 1; color: #1890ff; }
        table td { padding: 10px 16px; border-bottom: 1px solid #f0f0f0; }
        table tr:hover td { background: #fafafa; }
        .col-id { width: 60px; color: #999; }
        .col-user { width: 70px; }
        .col-ip { width: 165px; font-family: monospace; white-space: nowrap; }
        .col-time { width: 160px; white-space: nowrap; }
        .col-actions { width: 100px; text-align: center; }
        .user-agent-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .col-region { min-width: 160px; }
        .alive-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ip-lookup { margin-left: 6px; color: #1890ff; }
        .ip-lookup:hover { color: #40a9ff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #f0f0f0; }
        .badge-country { background: #e6f7ff; color: #1890ff; }
        .badge-isp { background: #f6ffed; color: #52c41a; }
        .badge-type { background: #f0f0f0; color: #666; }
        .badge-enable { background: #f6ffed; color: #52c41a; }
        .badge-disable { background: #fff2f0; color: #ff4d4f; }
        .badge-success { background: #f6ffed; color: #52c41a; }
        .badge-warning { background: #fffbe6; color: #faad14; }
        .badge-danger { background: #fff2f0; color: #ff4d4f; }
        .text-muted { color: #999; }

        .pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 0 0; }
        .pagination-info { font-size: 13px; color: #888; }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .pagination-controls button { min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
        .pagination-controls span { font-size: 13px; color: #555; padding: 0 4px; }
        .page-size-select { padding: 4px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; outline: none; }

        .loading { text-align: center; padding: 60px 0; color: #999; }
        .loading i { font-size: 32px; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .empty-state { text-align: center; padding: 60px 0; color: #ccc; }
        .empty-state i { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }
        .error-state { text-align: center; padding: 60px 0; color: #ff4d4f; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .toolbar-title { font-size: 15px; font-weight: 600; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: #fff; border-radius: 8px; width: 560px; max-width: 90vw; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #f0f0f0; }
        .modal-header h3 { font-size: 16px; }
        .modal-close { cursor: pointer; font-size: 18px; color: #999; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 20px 24px; }
        .modal-footer { padding: 12px 24px; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; gap: 8px; }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 4px; }
        .form-row input, .form-row select { width: 100%; padding: 7px 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; outline: none; }
        .form-row input:focus, .form-row select:focus { border-color: #1890ff; box-shadow: 0 0 0 2px rgba(24,144,255,0.2); }
        .form-row .hint { font-size: 11px; color: #999; margin-top: 2px; }
        .form-inline { display: flex; gap: 10px; }
        .form-inline .form-row { flex: 1; }

        .server-host { font-family: monospace; font-size: 12px; color: #1890ff; }
        .arrow { color: #999; margin: 0 6px; }
        .risk-sortable { cursor: pointer; user-select: none; }
        .risk-sortable:hover { color: #1890ff; }
        .risk-sortable i.fa-sort { margin-left: 4px; opacity: 0.3; font-size: 11px; }
        .risk-sortable.active i.fa-sort { opacity: 1; color: #1890ff; }
        .filter-group .risk-types { display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: center; min-height: 33px; }
        .filter-group .risk-type { display: inline-flex; flex-direction: row; align-items: center; gap: 5px; font-size: 13px; color: #555; font-weight: 400; cursor: pointer; }
        .filter-group .risk-type input[type="checkbox"],
        .filter-group .risk-type input[type="radio"] { margin: 0; min-width: 0; width: auto; padding: 0; }
        .filter-group .risk-type .risk-threshold { width: 58px; min-width: 0; padding: 3px 6px; font-size: 12px; }
        .api-list { display: flex; flex-direction: column; gap: 8px; }
        .api-row { display: flex; gap: 8px; align-items: center; }
        .api-row .api-url { flex: 1; min-width: 0; padding: 6px 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; }
        .api-row .btn { flex: 0 0 auto; }
        .provider-list { display: flex; flex-wrap: wrap; gap: 10px 24px; padding: 4px 0 8px; }
        .provider-item { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #555; cursor: pointer; }
        .provider-item input { margin: 0; }
        .risk-tags { display: inline-flex; flex-wrap: wrap; gap: 4px; }
        .risk-provinces { display: inline-block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }
        .mismatch-row td { background: #fff1f0; }
        .mismatch-province { color: #ff4d4f; font-weight: 600; }
        .danger-link { color: #ff4d4f; }
        .danger-link:hover { color: #ff7875; }

        .distribution-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px; }
        .distribution-toolbar .group { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .distribution-toolbar label { font-size: 13px; color: #666; margin: 0; }
        .distribution-toolbar .btn.active { background: #1890ff; color: #fff; border-color: #1890ff; }
        .distribution-toolbar input[type="number"] { width: 90px; padding: 6px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; outline: none; }
        .distribution-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .summary-card { background: #fafafa; border: 1px solid #f0f0f0; border-radius: 6px; padding: 14px 16px; }
        .summary-card .label { font-size: 12px; color: #888; }
        .summary-card .value { font-size: 22px; font-weight: 600; color: #333; margin-top: 4px; }
        .summary-card .sub { font-size: 12px; color: #999; margin-top: 4px; }
        .detail-summary { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
        .detail-summary .summary-card { display: flex; flex-direction: column; justify-content: space-between; gap: 8px; padding: 10px 12px; }
        .detail-summary .summary-field { display: flex; align-items: flex-start; gap: 8px; min-width: 0; }
        .detail-summary .summary-field .label { flex: 0 0 52px; line-height: 18px; }
        .detail-summary .field-body { flex: 1; min-width: 0; }
        .detail-summary .value { font-size: 13px; line-height: 18px; font-weight: 600; color: #333; margin-top: 0; overflow-wrap: anywhere; word-break: normal; }
        .detail-summary .sub { font-size: 11px; line-height: 15px; margin-top: 1px; }
        .distribution-map-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; }
        .china-map { width: 100%; height: 600px; }
        .province-rank { max-height: 600px; overflow: auto; border: 1px solid #f0f0f0; border-radius: 6px; }
        .province-rank-item { display: flex; justify-content: space-between; padding: 9px 12px; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
        .province-rank-item:last-child { border-bottom: none; }
        .province-rank-item:hover { background: #fafafa; }
        .province-rank-item .name { color: #555; }
        .province-rank-item .count { color: #1890ff; font-weight: 600; }

        @media (max-width: 900px) {
            .distribution-map-wrap { grid-template-columns: 1fr; }
            .china-map { height: 420px; }
            .province-rank { max-height: 360px; }
        }

        @media (max-width: 768px) {
            .filter-group input, .filter-group select { min-width: 100px; }
            .modal { width: 95vw; }
            .form-inline { flex-direction: column; }
        }
    </style>
</head>
<body>
<header class="header">
    <h1>用户画像</h1>
</header>

<div class="content">
    <div class="card">
        <div class="card-body">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tabLogs">订阅记录</button>
                <button class="tab-btn" data-tab="tabDistribution">用户分布</button>
                <button class="tab-btn" data-tab="tabDetail">用户详情</button>
                <button class="tab-btn" data-tab="tabRules">替换规则</button>
                <button class="tab-btn" data-tab="tabRisk">风险筛查</button>
                <button class="tab-btn" data-tab="tabSettings">设置</button>
            </div>

            <div id="tabLogs" class="tab-content active">
                <div class="filter-form" id="filterForm">
                    <div class="filter-group">
                        <label>时间范围</label>
                        <select id="filterDateRange">
                            <option value="">全部</option>
                            <option value="today">今天</option>
                            <option value="7d">近7天</option>
                            <option value="30d">近30天</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>User ID</label>
                        <input type="text" id="filterUserId" placeholder="精确搜索，支持 > < >= <=">
                    </div>
                    <div class="filter-group">
                        <label>User-Agent</label>
                        <input type="text" id="filterUserAgent" placeholder="关键字搜索，FULL:前缀精确匹配">
                    </div>
                    <div class="filter-group">
                        <label>IP 地址</label>
                        <input type="text" id="filterIp" placeholder="关键字搜索">
                    </div>
                    <div class="filter-group">
                        <label>国家</label>
                        <select id="filterCountry">
                            <option value="">全部</option>
                            <option value="mainland">中国大陆</option>
                            <option value="other">其他</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>地区</label>
                        <select id="filterRegion" disabled>
                            <option value="">请先选择国家</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>城市</label>
                        <input type="text" id="filterCity" placeholder="关键字搜索">
                    </div>
                    <div class="filter-group">
                        <label>运营商</label>
                        <input type="text" id="filterIsp" placeholder="输入运营商关键字，如 电信 / 联通 / 移动">
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <div style="display:flex;gap:8px">
                            <button class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i>搜索</button>
                            <button class="btn btn-default" id="resetBtn"><i class="fas fa-undo"></i>重置</button>
                            <button class="btn btn-danger" id="clearBtn"><i class="fas fa-trash"></i>清除</button>
                        </div>
                    </div>
                </div>
                <div class="filter-chips" id="filterChips"></div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-id sortable" data-sort="id">ID <i class="fas fa-sort"></i></th>
                                <th class="col-user sortable" data-sort="user_id">User <i class="fas fa-sort"></i></th>
                                <th class="col-ip sortable" data-sort="ip">订阅IP <i class="fas fa-sort"></i></th>
                                <th class="col-region sortable" data-sort="ip_region">地区 <i class="fas fa-sort"></i></th>
                                <th>在线记录</th>
                                <th class="sortable" data-sort="user_agent">User-Agent <i class="fas fa-sort"></i></th>
                                <th class="col-time sortable active" data-sort="created_at">时间 <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="7"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">共 0 条</div>
                    <div class="pagination-controls">
                        <span>每页</span>
                        <select class="page-size-select" id="pageSizeSelect">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>条</span>
                        <button class="btn btn-default btn-sm" id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
                        <span id="pageInfo">0/0</span>
                        <button class="btn btn-default btn-sm" id="nextPage" disabled><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div id="tabDistribution" class="tab-content">
                <div class="distribution-toolbar">
                    <div class="group" id="distributionRangeGroup">
                        <label>数据范围</label>
                        <button class="btn btn-default btn-sm active" data-range="1h">1小时</button>
                        <button class="btn btn-default btn-sm" data-range="1d">1天</button>
                        <button class="btn btn-default btn-sm" data-range="7d">7天</button>
                        <button class="btn btn-default btn-sm" data-range="30d">30天</button>
                    </div>
                    <div class="group" id="distributionProviderGroup">
                        <label>运营商</label>
                        <button class="btn btn-default btn-sm active" data-provider="all">全部</button>
                        <button class="btn btn-default btn-sm" data-provider="telecom">电信</button>
                        <button class="btn btn-default btn-sm" data-provider="unicom">联通</button>
                        <button class="btn btn-default btn-sm" data-provider="mobile">移动</button>
                        <button class="btn btn-default btn-sm" data-provider="other">其他</button>
                    </div>
                    <div class="group">
                        <label>保留</label>
                        <input type="number" id="cleanupDays" value="30" min="1" max="3650">
                        <label>天</label>
                        <button class="btn btn-danger btn-sm" id="cleanupHistoryBtn"><i class="fas fa-trash"></i>清理历史数据</button>
                    </div>
                </div>

                <div class="distribution-summary">
                    <div class="summary-card">
                        <div class="label" id="distTotalLabel">去重用户</div>
                        <div class="value" id="distTotal">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">覆盖省份</div>
                        <div class="value" id="distProvinces">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">电信</div>
                        <div class="value" id="distTelecom">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">联通</div>
                        <div class="value" id="distUnicom">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">移动</div>
                        <div class="value" id="distMobile">0</div>
                    </div>
                    <div class="summary-card">
                        <div class="label">其他</div>
                        <div class="value" id="distOther">0</div>
                    </div>
                </div>

                <div class="distribution-map-wrap">
                    <div id="chinaMap" class="china-map"></div>
                    <div class="province-rank" id="provinceRank">
                        <div class="empty-state"><i class="fas fa-spinner"></i><p>加载中...</p></div>
                    </div>
                </div>
            </div>

            <div id="tabDetail" class="tab-content">
                <div class="filter-form">
                    <div class="filter-group">
                        <label>User ID</label>
                        <input type="text" id="detailUserId" placeholder="输入用户 ID 查询">
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <div style="display:flex;gap:8px">
                            <button class="btn btn-primary" id="detailSearchBtn"><i class="fas fa-search"></i>查询</button>
                        </div>
                    </div>
                </div>

                <div id="detailEmpty" class="empty-state">
                    <i class="fas fa-user"></i>
                    <p>输入用户 ID 查看该用户的订阅记录和在线记录</p>
                </div>

                <div id="detailResult" style="display:none">
                    <div class="distribution-summary detail-summary" id="detailSummary"></div>

                    <div class="filter-form" style="margin-top:20px">
                        <div class="filter-group" style="min-width:220px">
                            <label>定位数据源</label>
                            <select id="detailResolveSource"></select>
                        </div>
                        <div class="filter-group" style="padding-bottom:1px">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary" id="detailResolveBtn"><i class="fas fa-sync"></i>重新定位全部记录</button>
                        </div>
                        <div class="filter-group" style="flex:1;min-width:240px">
                            <label>状态</label>
                            <div id="detailResolveStatus" style="font-size:13px;color:#555;padding:6px 0">选择数据源后点击重新定位</div>
                        </div>
                    </div>

                    <div class="toolbar" style="margin-top:20px">
                        <span class="toolbar-title">订阅记录 <span class="text-muted" id="detailSubTip" style="font-weight:400;font-size:12px"></span></span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th class="col-ip">订阅IP</th>
                                    <th class="col-region">地区</th>
                                    <th>User-Agent</th>
                                    <th class="col-time">时间</th>
                                    <th class="col-actions">操作</th>
                                </tr>
                            </thead>
                            <tbody id="detailSubBody"></tbody>
                        </table>
                    </div>

                    <div class="toolbar" style="margin-top:20px">
                        <span class="toolbar-title">在线记录 <span class="text-muted" id="detailAliveTip" style="font-weight:400;font-size:12px"></span></span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th class="col-ip">IP</th>
                                    <th class="col-region">地区</th>
                                    <th class="col-time">最近在线时间</th>
                                    <th class="col-actions">操作</th>
                                </tr>
                            </thead>
                            <tbody id="detailAliveBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="tabRules" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">替换规则 <span class="text-muted" style="font-weight:400;font-size:12px">按 sort 优先级，取第一条匹配；白名单规则匹配后跳过该服务器</span></span>
                    <button class="btn btn-primary btn-sm" id="addRuleBtn"><i class="fas fa-plus"></i>添加规则</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:50px">排序</th>
                                <th>备注</th>
                                <th>匹配 UA</th>
                                <th>匹配 国家</th>
                                <th>省份</th>
                                <th>城市</th>
                                <th>运营商</th>
                                <th>匹配 User ID</th>
                                <th>原 Host</th>
                                <th>替换为</th>
                                <th>替换端口</th>
                                <th>类型</th>
                                <th>状态</th>
                                <th class="col-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody id="rulesBody">
                            <tr><td colspan="14"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tabRisk" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">风险筛查</span>
                </div>
                <div class="filter-form" id="riskFilterForm">
                    <div class="filter-group">
                        <label>风险类型</label>
                        <div class="risk-types">
                            <label class="risk-type"><input type="checkbox" class="risk-type-check" value="mismatch" checked> 省份不一致</label>
                            <label class="risk-type"><input type="checkbox" class="risk-type-check" value="online_province" checked> 在线多省 ≥
                                <input type="number" class="risk-threshold" id="riskProvinceThreshold" value="3" min="1">
                            </label>
                            <label class="risk-type"><input type="checkbox" class="risk-type-check" value="online_ip" checked> 在线多 IP ≥
                                <input type="number" class="risk-threshold" id="riskIpThreshold" value="5" min="1">
                            </label>
                            <label class="risk-type"><input type="checkbox" class="risk-type-check" value="sub_province"> 订阅多省 ≥
                                <input type="number" class="risk-threshold" id="riskSubProvinceThreshold" value="3" min="1">
                            </label>
                            <label class="risk-type"><input type="checkbox" class="risk-type-check" value="overseas"> 境外/港澳台</label>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>时间范围</label>
                        <select id="riskDays">
                            <option value="7">近7天</option>
                            <option value="30" selected>近30天</option>
                            <option value="90">近90天</option>
                            <option value="0">全部</option>
                        </select>
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <div style="display:flex;gap:8px">
                            <button class="btn btn-primary" id="riskSearchBtn"><i class="fas fa-search"></i>筛查</button>
                        </div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:80px">User ID</th>
                                <th class="risk-sortable" data-sort="risk_count">风险类型 <i class="fas fa-sort"></i></th>
                                <th class="risk-sortable" data-sort="sub_region_count">订阅省份 <i class="fas fa-sort"></i></th>
                                <th class="risk-sortable" data-sort="ua_count">UA种类 <i class="fas fa-sort"></i></th>
                                <th class="risk-sortable" data-sort="alive_province_count">在线省份 <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="riskBody">
                            <tr><td colspan="5"><div class="empty-state"><i class="fas fa-shield-alt"></i><p>点击"筛查"开始分析</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="riskPagination" style="display:none">
                    <div class="pagination-info"><span id="riskTotalInfo">共 0 条</span></div>
                    <div class="pagination-controls">
                        <button class="btn btn-default btn-sm" id="riskPrevPage" disabled><i class="fas fa-chevron-left"></i></button>
                        <span id="riskPageInfo">0/0</span>
                        <button class="btn btn-default btn-sm" id="riskNextPage" disabled><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div id="tabSettings" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">IP 查询接口</span>
                </div>
                <div class="filter-form">
                    <div class="filter-group" style="flex:1;min-width:320px">
                        <label>接口地址</label>
                        <input type="text" id="settingIpLookup" placeholder="https://iplark.com/">
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px;margin:-4px 0 24px">
                    支持 <code>{ip}</code> 占位符，例如 <code>https://iplark.com/{ip}</code>；不写占位符时按「接口地址 + IP」拼接。
                </p>

                <div class="toolbar">
                    <span class="toolbar-title">IP 归属地来源</span>
                </div>
                <div class="filter-form">
                    <div class="filter-group" style="min-width:260px">
                        <label>IPv4</label>
                        <div class="risk-types">
                            <label class="risk-type"><input type="radio" name="settingSourceV4" value="local" checked> 本地 IP 库</label>
                            <label class="risk-type"><input type="radio" name="settingSourceV4" value="online"> 在线接口</label>
                        </div>
                    </div>
                    <div class="filter-group" style="min-width:260px">
                        <label>IPv6</label>
                        <div class="risk-types">
                            <label class="risk-type"><input type="radio" name="settingSourceV6" value="local" checked> 本地 IP 库</label>
                            <label class="risk-type"><input type="radio" name="settingSourceV6" value="online"> 在线接口</label>
                        </div>
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px;margin:-4px 0 16px">
                    选择「在线接口」时优先调用下方接口，全部失败后回退本地 IP 库（单用户重新定位除外）。
                </p>

                <div class="filter-form">
                    <div class="filter-group" style="flex:1;min-width:340px">
                        <label>IPv4 库路径</label>
                        <input type="text" id="settingIpDbV4" placeholder="留空使用程序目录下的 ipdata.xdb">
                    </div>
                    <div class="filter-group" style="flex:1;min-width:340px">
                        <label>IPv6 库路径</label>
                        <input type="text" id="settingIpDbV6" placeholder="留空使用 ipdata6.xdb，可留空">
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px;margin:-4px 0 24px">
                    留空时使用程序目录下的 ipdata.xdb / ipdata6.xdb；相对路径按项目根目录解析。
                </p>

                <div class="toolbar">
                    <span class="toolbar-title">在线查询接口 <span class="text-muted" style="font-weight:400;font-size:12px">按顺序尝试，失败自动切换下一个</span></span>
                </div>
                <div id="providerList" class="provider-list"></div>

                <div class="filter-form" style="margin-top:12px">
                    <div class="filter-group" style="min-width:240px">
                        <label>测试 IP</label>
                        <input type="text" id="testIpInput" placeholder="例如 114.114.114.114">
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <button class="btn btn-default" id="testIpBtn"><i class="fas fa-vial"></i>测试</button>
                    </div>
                    <div class="filter-group" style="flex:1;min-width:240px">
                        <label>解析结果</label>
                        <div id="testIpResult" style="font-size:13px;color:#555;padding:6px 0">-</div>
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px;margin:-4px 0 24px">
                    保存后对订阅日志、在线记录、订单、工单提醒、用户详情、重建缓存全部生效。
                </p>

                <div class="toolbar">
                    <span class="toolbar-title">数据维护</span>
                </div>
                <div class="filter-form">
                    <div class="filter-group">
                        <label>重建缓存</label>
                        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                            <button class="btn btn-primary" id="rebuildBtnV4"><i class="fas fa-sync"></i>重建 IPv4</button>
                            <button class="btn btn-primary" id="rebuildBtnV6"><i class="fas fa-sync"></i>重建 IPv6</button>
                            <span id="rebuildStatus" class="text-muted" style="font-size:13px">重新解析订阅记录和在线记录中的 IP 国家 / 地区 / 省市 / 运营商</span>
                        </div>
                    </div>
                </div>

                <div style="margin-top:24px;display:flex;gap:8px">
                    <button class="btn btn-primary" id="saveSettingsBtn"><i class="fas fa-save"></i>保存设置</button>
                    <button class="btn btn-default" id="resetSettingsBtn"><i class="fas fa-undo"></i>恢复默认</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ruleModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="ruleModalTitle">添加规则</h3>
            <span class="modal-close" onclick="closeRuleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ruleId">
            <div class="form-row">
                <label>备注 <span class="text-muted">*</span></label>
                <input type="text" id="ruleRemark" placeholder="例如：国内用户走国内入口">
            </div>
            <div class="form-row">
                <label>排序 <span class="text-muted">*</span></label>
                <input type="number" id="ruleSort" value="0" min="0">
            </div>
            <div class="form-row">
                <label>匹配 User ID <span class="hint">精确匹配，留空=不限制</span></label>
                <input type="text" id="ruleUserId" placeholder="例如：123 或 NOT:5">
            </div>
            <div class="form-row">
                <label>匹配 User-Agent <span class="hint">模糊匹配，留空=不限制</span></label>
                <input type="text" id="ruleUserAgent" placeholder="例如：ClashMeta">
            </div>
            <div class="form-inline">
                <div class="form-row">
                    <label>匹配 国家</label>
                    <input type="text" id="ruleCountry" placeholder="留空=不限制">
                </div>
                <div class="form-row">
                    <label>匹配 省份</label>
                    <input type="text" id="ruleRegion" placeholder="留空=不限制">
                </div>
            </div>
            <div class="form-inline">
                <div class="form-row">
                    <label>匹配 城市</label>
                    <input type="text" id="ruleCity" placeholder="留空=不限制">
                </div>
                <div class="form-row">
                    <label>匹配 运营商</label>
                    <input type="text" id="ruleIsp" placeholder="留空=不限制">
                </div>
            </div>
            <div class="form-row">
                <label>原 Host <span class="hint">留空=替换所有节点</span></label>
                <input type="text" id="ruleOriginalHost" placeholder="例如：sg.example.com">
            </div>
            <div class="form-row">
                <label>替换 Host <span class="text-muted">*</span></label>
                <input type="text" id="ruleReplaceHost" placeholder="例如：hk.example.com">
            </div>
            <div class="form-row">
                <label>替换端口 <span class="hint">留空=不替换端口</span></label>
                <input type="number" id="ruleReplacePort" placeholder="例如：443" min="1" max="65535">
            </div>
            <div class="form-row">
                <label>类型</label>
                <select id="ruleType">
                    <option value="0">替换</option>
                    <option value="1">白名单</option>
                </select>
            </div>
            <div class="form-row">
                <label>状态</label>
                <select id="ruleEnable">
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" onclick="closeRuleModal()">取消</button>
            <button class="btn btn-primary" id="saveRuleBtn">保存</button>
        </div>
    </div>
</div>

<script>
var securePath = '{{$secure_path}}';
var apiBase = '/api/v1/' + securePath;

var authToken = window.localStorage.getItem('admin_authorization');
if (!authToken) {
    window.location.replace('/' + securePath + '#/login');
    throw new Error('redirect to admin login');
}

function displayRuleValue(val) {
    if (!val) return '<span class="text-muted">-</span>';
    if (val.startsWith('NOT:')) return '<span class="text-danger">NOT:</span> ' + val.substring(4);
    return val;
}

function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['authorization'] = authToken;
    return fetch(url, options);
}

// =========== Distribution ===========
var distributionState = { range: '1h', provider: 'all', data: null, chart: null };
var chinaMapPromise = null;

function providerLabel(provider) {
    var labels = { telecom: '电信', unicom: '联通', mobile: '移动', other: '其他' };
    return labels[provider] || provider;
}

var mainlandProvinceNames = [
    '北京', '天津', '河北', '山西', '内蒙古', '辽宁', '吉林', '黑龙江',
    '上海', '江苏', '浙江', '安徽', '福建', '江西', '山东', '河南',
    '湖北', '湖南', '广东', '广西', '海南', '重庆', '四川', '贵州',
    '云南', '西藏', '陕西', '甘肃', '青海', '宁夏', '新疆'
];

function shortProvinceName(name) {
    var aliases = {
        '内蒙古自治区': '内蒙古',
        '广西壮族自治区': '广西',
        '西藏自治区': '西藏',
        '宁夏回族自治区': '宁夏',
        '新疆维吾尔自治区': '新疆',
        '香港特别行政区': '香港',
        '澳门特别行政区': '澳门'
    };
    if (aliases[name]) return aliases[name];
    if (!name) return '';
    return name.replace(/(省|市)$/, '');
}

function polygonBBoxArea(polygon) {
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    (function walk(coords) {
        if (typeof coords[0] === 'number') {
            var x = coords[0], y = coords[1];
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
            return;
        }
        for (var i = 0; i < coords.length; i++) walk(coords[i]);
    })(polygon);
    return Math.max(0, maxX - minX) * Math.max(0, maxY - minY);
}

// 海南要素包含 133 个多边形（含南海岛礁），只保留面积最大的主岛
function keepMainIsland(feature) {
    var geometry = feature.geometry;
    if (!geometry || geometry.type !== 'MultiPolygon' || !geometry.coordinates || !geometry.coordinates.length) return;
    var best = 0, bestArea = -1;
    for (var i = 0; i < geometry.coordinates.length; i++) {
        var area = polygonBBoxArea(geometry.coordinates[i]);
        if (area > bestArea) {
            bestArea = area;
            best = i;
        }
    }
    geometry.coordinates = [geometry.coordinates[best]];
}

function ensureChinaMap() {
    if (chinaMapPromise) return chinaMapPromise;
    if (typeof echarts === 'undefined') {
        return Promise.reject(new Error('echarts not loaded'));
    }
    chinaMapPromise = fetch('/assets/admin/vendor/china.json?v={{$version}}')
        .then(function(response) {
            if (!response.ok) throw new Error('map http error');
            return response.json();
        })
        .then(function(geo) {
            geo.features = (geo.features || []).filter(function(feature) {
                if (!feature.properties || !feature.properties.name) {
                    return false;
                }
                var name = shortProvinceName(feature.properties.name);
                if (mainlandProvinceNames.indexOf(name) === -1) {
                    return false;
                }
                feature.properties.name = name;
                if (name === '海南') {
                    keepMainIsland(feature);
                }
                return true;
            });
            // 不使用 'china' 作为地图名，避免 ECharts 自动注入内置的“南海诸岛”小图
            echarts.registerMap('china-mainland', geo);
            return geo;
        })
        .catch(function(error) {
            chinaMapPromise = null;
            throw error;
        });
    return chinaMapPromise;
}

function setDistributionActive(selector, attr, value) {
    document.querySelectorAll(selector + ' .btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.getAttribute(attr) === value);
    });
}

function renderProvinceRank(rank) {
    var box = document.getElementById('provinceRank');
    if (!rank.length) {
        box.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>暂无数据</p></div>';
        return;
    }
    var html = '';
    rank.forEach(function(item) {
        html += '<div class="province-rank-item">'
            + '<span class="name">' + item.name + '</span>'
            + '<span class="count">' + item.total + '</span>'
            + '</div>';
    });
    box.innerHTML = html;
}

function renderChinaMap(rank) {
    var chart = distributionState.chart;
    if (!chart) {
        var mapElement = document.getElementById('chinaMap');
        mapElement.innerHTML = '';
        chart = echarts.init(mapElement);
        distributionState.chart = chart;
    }
    var max = Math.max.apply(null, [1].concat(rank.map(function(item) { return item.total; })));
    chart.setOption({
        tooltip: {
            trigger: 'item',
            formatter: function(params) {
                return params.name + '<br/>' + (params.value || 0) + ' 用户';
            }
        },
        visualMap: {
            min: 0,
            max: max,
            left: 'left',
            bottom: 20,
            text: ['高', '低'],
            calculable: true,
            inRange: { color: ['#e6f7ff', '#1890ff'] }
        },
        series: [{
            name: '用户分布',
            type: 'map',
            map: 'china-mainland',
            roam: false,
            layoutCenter: ['50%', '50%'],
            layoutSize: '110%',
            aspectScale: 0.75,
            label: { show: false },
            emphasis: { label: { show: true } },
            data: rank.map(function(item) {
                return { name: item.name, value: item.total };
            })
        }]
    }, true);
    setTimeout(function() { chart.resize(); }, 0);
}

function renderDistribution() {
    var data = distributionState.data;
    if (!data) return;
    var provider = distributionState.provider;
    var providers = data.providers || {};
    var regions = data.regions || [];
    var selectedTotal = provider === 'all' ? (data.total || 0) : (providers[provider] || 0);
    document.getElementById('distTotal').textContent = selectedTotal;
    document.getElementById('distTotalLabel').textContent = provider === 'all' ? '去重用户' : '去重用户 · ' + providerLabel(provider);
    document.getElementById('distTelecom').textContent = providers.telecom || 0;
    document.getElementById('distUnicom').textContent = providers.unicom || 0;
    document.getElementById('distMobile').textContent = providers.mobile || 0;
    document.getElementById('distOther').textContent = providers.other || 0;

    var rank = regions.map(function(item) {
        return {
            name: item.name,
            total: provider === 'all' ? (item.total || 0) : (item[provider] || 0)
        };
    }).filter(function(item) {
        return item.total > 0;
    }).sort(function(a, b) {
        return b.total - a.total;
    });

    document.getElementById('distProvinces').textContent = rank.length;
    renderProvinceRank(rank);

    ensureChinaMap().then(function() {
        renderChinaMap(rank);
    }).catch(function() {
        var mapBox = document.getElementById('chinaMap');
        mapBox.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>地图加载失败，请查看右侧省份排行</p></div>';
    });
}

function loadDistribution() {
    var rankBox = document.getElementById('provinceRank');
    rankBox.innerHTML = '<div class="empty-state"><i class="fas fa-spinner"></i><p>加载中...</p></div>';
    apiFetch(apiBase + '/user_portrait/distribution?range=' + encodeURIComponent(distributionState.range))
        .then(function(response) { return response.json(); })
        .then(function(result) {
            distributionState.data = result.data || { total: 0, providers: {}, regions: [] };
            renderDistribution();
        })
        .catch(function() {
            rankBox.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div>';
        });
}

document.querySelectorAll('#distributionRangeGroup .btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        distributionState.range = this.getAttribute('data-range');
        setDistributionActive('#distributionRangeGroup', 'data-range', distributionState.range);
        loadDistribution();
    });
});

document.querySelectorAll('#distributionProviderGroup .btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        distributionState.provider = this.getAttribute('data-provider');
        setDistributionActive('#distributionProviderGroup', 'data-provider', distributionState.provider);
        renderDistribution();
    });
});

var cleanupDaysInput = document.getElementById('cleanupDays');
var savedCleanupDays = window.localStorage.getItem('user_portrait_cleanup_days');
if (savedCleanupDays) {
    cleanupDaysInput.value = savedCleanupDays;
}
cleanupDaysInput.addEventListener('change', function() {
    window.localStorage.setItem('user_portrait_cleanup_days', this.value);
});

document.getElementById('cleanupHistoryBtn').addEventListener('click', function() {
    var days = parseInt(cleanupDaysInput.value, 10);
    if (!days || days < 1) {
        alert('请输入大于 0 的天数');
        return;
    }
    if (days > 3650) {
        alert('天数不能超过 3650');
        return;
    }
    if (!confirm('确认清理 ' + days + ' 天前的用户分布历史数据？此操作不可恢复。')) {
        return;
    }
    apiFetch(apiBase + '/user_portrait/distributionClear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ days: days })
    }).then(function(response) { return response.json(); })
        .then(function(result) {
            alert('已清理 ' + (result.count || 0) + ' 条历史记录');
            loadDistribution();
        })
        .catch(function() {
            alert('清理失败');
        });
});

window.addEventListener('resize', function() {
    if (distributionState.chart) {
        distributionState.chart.resize();
    }
});

// =========== Tab switching ===========
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById(this.getAttribute('data-tab')).classList.add('active');
    });
});

// =========== Log state & functions ===========
var state = { current: 1, pageSize: 10, sort: 'created_at', sortType: 'DESC', filters: {}, total: 0 };
var logRequestId = 0;
var filterDebounceTimer = null;
var filterControlMap = {
    user_id: 'filterUserId',
    user_agent: 'filterUserAgent',
    ip: 'filterIp',
    country_group: 'filterCountry',
    area: 'filterRegion',
    ip_city: 'filterCity',
    ip_isp: 'filterIsp',
    date_range: 'filterDateRange'
};
var filterLabels = {
    user_id: 'User ID',
    user_agent: 'UA',
    ip: 'IP',
    country_group: '国家',
    area: '地区',
    ip_city: '城市',
    ip_isp: '运营商',
    date_range: '时间'
};
var dateRangeLabels = { today: '今天', '7d': '近7天', '30d': '近30天' };
var countryGroupLabels = { mainland: '中国大陆', other: '其他' };
var filterOptionsData = { mainland_regions: [], other_regions: [] };
var areaLabelMap = {};

var ipLookupBase = @json($ip_lookup_url ?? 'https://iplark.com/');
var defaultIpLookup = 'https://iplark.com/';

function ipLookupUrl(ip) {
    var base = (ipLookupBase || defaultIpLookup).trim();
    if (base.indexOf('{ip}') !== -1) {
        return base.replace('{ip}', encodeURIComponent(ip));
    }
    return base.replace(/\/+$/, '') + '/' + encodeURIComponent(ip);
}

function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
}

function syncUrlFromState() {
    var params = new URLSearchParams();
    params.set('current', state.current);
    params.set('pageSize', state.pageSize);
    params.set('sort', state.sort);
    params.set('sort_type', state.sortType);
    Object.keys(state.filters || {}).forEach(function(key) {
        if (state.filters[key]) params.set(key, state.filters[key]);
    });
    var query = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
}

function restoreStateFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var detailMode = params.get('tab') === 'detail';
    ['user_id', 'user_agent', 'ip', 'ip_city', 'ip_isp', 'date_range'].forEach(function(key) {
        if (detailMode && key === 'user_id') return;
        var value = params.get(key);
        if (value) {
            var control = document.getElementById(filterControlMap[key]);
            if (control) control.value = value;
        }
    });

    var countryGroup = params.get('country_group') || '';
    var area = params.get('area') || '';
    if (!countryGroup) {
        var oldCountry = params.get('ip_country') || '';
        var oldRegion = params.get('ip_region') || '';
        if (oldCountry === '中国') {
            if (['香港', '澳门', '台湾'].indexOf(oldRegion) !== -1) {
                countryGroup = 'other';
                area = 'region:' + oldRegion;
            } else {
                countryGroup = 'mainland';
                area = oldRegion;
            }
        } else if (oldCountry) {
            countryGroup = 'other';
            area = 'country:' + oldCountry;
        } else if (oldRegion) {
            if (['香港', '澳门', '台湾'].indexOf(oldRegion) !== -1) {
                countryGroup = 'other';
                area = 'region:' + oldRegion;
            } else {
                countryGroup = 'mainland';
                area = oldRegion;
            }
        }
    }
    document.getElementById('filterCountry').value = countryGroup;
    populateRegionOptions(area);

    var current = parseInt(params.get('current'), 10);
    if (current > 0) state.current = current;
    var pageSize = parseInt(params.get('pageSize'), 10);
    if ([10, 20, 50, 100].indexOf(pageSize) !== -1) {
        state.pageSize = pageSize;
        document.getElementById('pageSizeSelect').value = pageSize;
    }
    var sortWhitelist = ['id', 'user_id', 'ip', 'ip_country', 'ip_region', 'ip_city', 'ip_isp', 'user_agent', 'created_at'];
    var sort = params.get('sort');
    if (sortWhitelist.indexOf(sort) !== -1) state.sort = sort;
    var sortType = params.get('sort_type');
    if (sortType === 'ASC' || sortType === 'DESC') state.sortType = sortType;
    state.filters = buildFilters();
    document.querySelectorAll('th.sortable').forEach(function(th) {
        th.classList.toggle('active', th.getAttribute('data-sort') === state.sort);
    });
}

function renderFilterChips() {
    var box = document.getElementById('filterChips');
    if (!box) return;
    var filters = state.filters || {};
    var keys = Object.keys(filters).filter(function(key) { return filters[key]; });
    if (!keys.length) {
        box.innerHTML = '';
        return;
    }
    var html = '';
    keys.forEach(function(key) {
        var value = filters[key];
        if (key === 'date_range') {
            value = dateRangeLabels[value] || value;
        } else if (key === 'country_group') {
            value = countryGroupLabels[value] || value;
        } else if (key === 'area') {
            value = areaLabelMap[value] || value.replace(/^(country|region):/, '');
        }
        html += '<span class="filter-chip">'
            + escapeHtml((filterLabels[key] || key) + ': ' + value)
            + '<span class="remove" data-key="' + escapeHtml(key) + '">&times;</span>'
            + '</span>';
    });
    html += '<span class="filter-chip clear-all" id="clearAllFilters">清空全部</span>';
    box.innerHTML = html;
}

function scheduleLogSearch() {
    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(searchLogs, 500);
}

function fmtTime(ts) {
    if (!ts) return '-';
    var d = new Date(typeof ts === 'number' ? ts * 1000 : ts);
    if (isNaN(d.getTime())) return '-';
    var pad = function(n) { return n < 10 ? '0' + n : n; };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}

function truncate(str, len) {
    if (!str) return '-';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function populateRegionOptions(preserveValue) {
    var country = document.getElementById('filterCountry').value;
    var regionSel = document.getElementById('filterRegion');
    var previous = preserveValue !== undefined ? preserveValue : regionSel.value;
    while (regionSel.options.length) {
        regionSel.remove(0);
    }
    if (!country) {
        regionSel.disabled = true;
        regionSel.add(new Option('请先选择国家', ''));
        return;
    }
    regionSel.disabled = false;
    regionSel.add(new Option('全部', ''));
    var list = country === 'mainland' ? filterOptionsData.mainland_regions : filterOptionsData.other_regions;
    list.forEach(function(item) {
        if (typeof item === 'string') {
            regionSel.add(new Option(item, item));
        } else {
            regionSel.add(new Option(item.label, item.value));
        }
    });
    if (previous) {
        var exists = Array.prototype.some.call(regionSel.options, function(option) {
            return option.value === previous;
        });
        if (!exists) {
            var label = areaLabelMap[previous] || previous.replace(/^(country|region):/, '');
            regionSel.add(new Option(label, previous));
        }
        regionSel.value = previous;
    }
}

function loadFilterOptions() {
    return apiFetch(apiBase + '/user_portrait/getFilterOptions')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.data) return;
            filterOptionsData = {
                mainland_regions: res.data.mainland_regions || [],
                other_regions: res.data.other_regions || []
            };
            areaLabelMap = {};
            filterOptionsData.mainland_regions.forEach(function(region) {
                areaLabelMap[region] = region;
            });
            filterOptionsData.other_regions.forEach(function(item) {
                areaLabelMap[item.value] = item.label;
            });
            populateRegionOptions();
        }).catch(function() {});
}

function buildFilters() {
    var f = {};
    var dateRange = document.getElementById('filterDateRange').value;
    if (dateRange) f.date_range = dateRange;
    var userId = document.getElementById('filterUserId').value.trim();
    if (userId) f.user_id = userId;
    var ua = document.getElementById('filterUserAgent').value.trim();
    if (ua) f.user_agent = ua;
    var ip = document.getElementById('filterIp').value.trim();
    if (ip) f.ip = ip;
    var countryGroup = document.getElementById('filterCountry').value;
    if (countryGroup) f.country_group = countryGroup;
    var area = document.getElementById('filterRegion').value;
    if (countryGroup && area) f.area = area;
    var city = document.getElementById('filterCity').value.trim();
    if (city) f.ip_city = city;
    var isp = document.getElementById('filterIsp').value.trim();
    if (isp) f.ip_isp = isp;
    return f;
}

function loadLogData() {
    var requestId = ++logRequestId;
    var params = new URLSearchParams();
    params.set('current', state.current);
    params.set('pageSize', state.pageSize);
    params.set('sort', state.sort);
    params.set('sort_type', state.sortType);
    var filters = state.filters;
    Object.keys(filters).forEach(function(k) {
        if (filters[k]) params.set(k, filters[k]);
    });
    syncUrlFromState();

    var searchBtn = document.getElementById('searchBtn');
    searchBtn.disabled = true;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>搜索中';

    document.getElementById('tableBody').innerHTML = '<tr><td colspan="7"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';

    apiFetch(apiBase + '/user_portrait/fetch?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (requestId !== logRequestId) return;
            state.total = res.total || 0;
            renderLogTable(res.data || []);
        })
        .catch(function(err) {
            if (requestId !== logRequestId) return;
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="7"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        })
        .finally(function() {
            if (requestId === logRequestId) {
                searchBtn.disabled = false;
                searchBtn.innerHTML = '<i class="fas fa-search"></i>搜索';
            }
        });
}

var specialRegionMap = { '香港': '中国香港', '澳门': '中国澳门', '台湾': '中国台湾' };

function filterLink(filter, value, text) {
    return '<a href="javascript:void(0)" class="quick-filter" data-filter="' + filter +
        '" data-value="' + escapeHtml(value) + '">' + escapeHtml(text) + '</a>';
}

function regionParts(row) {
    var country = (row.ip_country || '').trim();
    var region = (row.ip_region || '').trim();
    var city = (row.ip_city || '').trim();
    var isp = (row.ip_isp || '').trim();
    var parts = [];

    // 港澳台单独显示，不按省市拼接
    var special = specialRegionMap[region] || specialRegionMap[country] || '';
    if (special) {
        var specialRegion = specialRegionMap[region] ? region : (specialRegionMap[country] ? country : '');
        parts.push({ filter: 'region', country: '中国', region: specialRegion, text: special });
        if (isp) parts.push({ filter: 'ip_isp', value: isp, text: isp });
        return parts;
    }

    // 中国大陆：省 + 市 + 运营商
    if (country === '中国') {
        // 直辖市的省和市同名（如 北京 北京），只显示一次
        var regionKey = region.replace(/(省|市)$/, '');
        var cityKey = city.replace(/(省|市|地区|自治州|盟)$/, '');
        if (region) parts.push({ filter: 'region', country: '中国', region: region, text: region });
        if (city && cityKey !== regionKey) parts.push({ filter: 'ip_city', value: city, text: city });
        if (isp) parts.push({ filter: 'ip_isp', value: isp, text: isp });
        return parts;
    }

    // 其他国家/地区：国家 + 运营商
    if (country) parts.push({ filter: 'country', country: country, text: country });
    if (isp) parts.push({ filter: 'ip_isp', value: isp, text: isp });
    return parts;
}

function regionCellHtml(row) {
    var parts = regionParts(row);
    if (!parts.length) return '<span class="text-muted">-</span>';
    return parts.map(function(part) {
        if (part.filter === 'region') {
            return '<a href="javascript:void(0)" class="quick-filter" data-filter="region" data-country="中国" data-region="' + escapeHtml(part.region) + '">' + escapeHtml(part.text) + '</a>';
        }
        if (part.filter === 'country') {
            return '<a href="javascript:void(0)" class="quick-filter" data-filter="country" data-country="' + escapeHtml(part.country) + '" data-region="">' + escapeHtml(part.text) + '</a>';
        }
        return filterLink(part.filter, part.value, part.text);
    }).join(' ');
}

function regionText(row) {
    var parts = regionParts(row);
    if (!parts.length) return '-';
    return parts.map(function(part) { return part.text; }).join(' ');
}

function regionSubText(info) {
    if (!info) return '';
    var text = regionText({
        ip_country: info.country,
        ip_region: info.region,
        ip_city: info.city,
        ip_isp: info.isp
    });
    return text === '-' ? '' : text;
}

function renderLogTable(data) {
    var tbody = document.getElementById('tableBody');
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无数据</p></div></td></tr>';
        updatePagination();
        return;
    }
    var html = '';
    data.forEach(function(row) {
        var ip = row.ip || '';
        var ua = row.user_agent || '';
        var alive = row.alive_provinces || [];
        var aliveText = alive.length ? alive.join('、') : '';
        html += '<tr>'
            + '<td class="col-id">' + escapeHtml(row.id) + '</td>'
            + '<td class="col-user"><a href="javascript:void(0)" onclick="openUserDetail(' + parseInt(row.user_id, 10) + ')" style="color:#1890ff">' + escapeHtml(row.user_id) + '</a></td>'
            + '<td class="col-ip">' + (ip
                ? '<a href="javascript:void(0)" class="quick-filter" data-filter="ip" data-value="' + escapeHtml(ip) + '">' + escapeHtml(ip) + '</a>'
                  + '<a class="ip-lookup" href="' + escapeHtml(ipLookupUrl(ip)) + '" target="_blank" rel="noopener noreferrer" title="查询此 IP"><i class="fas fa-search"></i></a>'
                : '-') + '</td>'
            + '<td class="col-region">' + regionCellHtml(row) + '</td>'
            + '<td class="alive-cell" title="' + escapeHtml(aliveText) + '">' + (aliveText ? escapeHtml(aliveText) : '<span class="text-muted">-</span>') + '</td>'
            + '<td class="user-agent-cell" title="' + escapeHtml(ua) + '">' + (ua ? '<a href="javascript:void(0)" class="quick-filter" data-filter="user_agent" data-value="' + escapeHtml(ua) + '">' + escapeHtml(truncate(ua, 40)) + '</a>' : '-') + '</td>'
            + '<td class="col-time">' + fmtTime(row.created_at) + '</td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    updatePagination();
}

function updatePagination() {
    var total = state.total;
    var page = state.current;
    var pageSize = state.pageSize;
    var totalPages = Math.ceil(total / pageSize) || 1;
    document.getElementById('paginationInfo').textContent = '共 ' + total + ' 条';
    document.getElementById('pageInfo').textContent = page + '/' + totalPages;
    document.getElementById('prevPage').disabled = page <= 1;
    document.getElementById('nextPage').disabled = page >= totalPages;
}

// =========== User detail ===========
var detailInited = false;

function initDetailTab() {
    if (detailInited) return;
    detailInited = true;
    document.getElementById('detailSearchBtn').addEventListener('click', searchDetail);
    document.getElementById('detailUserId').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') searchDetail();
    });
    document.getElementById('detailResolveBtn').addEventListener('click', resolveDetailIps);
    loadDetailSources();
    var params = new URLSearchParams(window.location.search);
    var userId = params.get('user_id');
    if (userId) {
        document.getElementById('detailUserId').value = userId;
    }
}

function loadDetailSources() {
    apiFetch(apiBase + '/user_portrait/getSettings')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || {};
            var providers = data.ip_providers || [];
            var enabled = data.ip_lookup_providers || providers.map(function(p) { return p.key; });
            var select = document.getElementById('detailResolveSource');
            var current = select.value;
            select.innerHTML = '';
            var local = document.createElement('option');
            local.value = 'local';
            local.textContent = '本地 IP 库';
            select.appendChild(local);
            providers.forEach(function(provider) {
                if (enabled.indexOf(provider.key) === -1) return;
                var option = document.createElement('option');
                option.value = 'provider:' + provider.key;
                option.textContent = provider.name;
                select.appendChild(option);
            });
            if (current) select.value = current;
        })
        .catch(function() {});
}

var detailResolving = false;

function resolveDetailIps() {
    if (detailResolving) return;
    var userId = parseInt(document.getElementById('detailUserId').value.trim(), 10);
    if (!userId || userId <= 0) {
        alert('请先查询用户');
        return;
    }
    var select = document.getElementById('detailResolveSource');
    var source = select.value || 'local';
    var label = select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent : '本地 IP 库';
    if (!confirm('确认用「' + label + '」重新定位该用户的订阅记录和在线记录？')) return;

    detailResolving = true;
    var btn = document.getElementById('detailResolveBtn');
    var status = document.getElementById('detailResolveStatus');
    btn.disabled = true;
    status.textContent = '准备中...';

    runResolve('sub', userId, source, 0, 0, 0, function() {
        runResolve('alive', userId, source, 0, 0, 0, function() {
            detailResolving = false;
            btn.disabled = false;
            status.textContent = '定位完成';
            searchDetail();
        });
    });
}

function runResolve(type, userId, source, lastId, doneCount, total, onDone) {
    var label = type === 'sub' ? '订阅记录' : '在线记录';
    var status = document.getElementById('detailResolveStatus');
    var params = new URLSearchParams();
    params.set('user_id', userId);
    params.set('type', type);
    params.set('source', source);
    params.set('last_id', lastId);
    params.set('limit', 200);

    apiFetch(apiBase + '/user_portrait/resolveUserIps', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) {
        if (r.ok) return r.json();
        return r.json().catch(function() { return {}; }).then(function(err) {
            throw new Error(err.message || ('HTTP ' + r.status));
        });
    }).then(function(res) {
        var data = res.data || {};
        if (data.total) total = data.total;
        var newDone = doneCount + (data.processed || 0);
        status.textContent = label + ' ' + newDone + (total ? ' / ' + total : '');
        if (data.done) {
            onDone();
        } else {
            runResolve(type, userId, source, data.last_id, newDone, total, onDone);
        }
    }).catch(function(err) {
        detailResolving = false;
        document.getElementById('detailResolveBtn').disabled = false;
        status.textContent = '定位失败：' + (err && err.message ? err.message : '请重试');
    });
}

function detailIpCell(ip) {
    if (!ip) return '-';
    return escapeHtml(ip)
        + '<a class="ip-lookup" href="' + escapeHtml(ipLookupUrl(ip)) + '" target="_blank" rel="noopener noreferrer" title="查询此 IP"><i class="fas fa-search"></i></a>';
}

function detailTip(shown, total) {
    total = total || shown;
    if (shown >= 200 && total > shown) {
        return '仅显示最近 ' + shown + ' 条，共 ' + total + ' 条';
    }
    return '共 ' + total + ' 条';
}

function searchDetail() {
    var input = document.getElementById('detailUserId');
    var userId = parseInt(input.value.trim(), 10);
    if (!userId || userId <= 0) {
        alert('请输入有效的用户 ID');
        return;
    }
    var btn = document.getElementById('detailSearchBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>查询中';

    apiFetch(apiBase + '/user_portrait/detail?user_id=' + userId)
        .then(function(r) {
            if (!r.ok) throw new Error('http ' + r.status);
            return r.json();
        })
        .then(function(res) {
            renderDetail(res.data || {});
        })
        .catch(function() {
            alert('查询失败，请确认用户 ID 是否正确');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i>查询';
        });
}

function renderDetail(data) {
    var user = data.user || {};
    var subLogs = data.sub_logs || [];
    var aliveIps = data.alive_ips || [];

    document.getElementById('detailEmpty').style.display = 'none';
    document.getElementById('detailResult').style.display = '';

    var summary = [
        { fields: [
            { label: '状态', value: user.banned ? '已封禁' : '正常' },
            { label: '邮箱', value: user.email || '-' }
        ] },
        { fields: [
            { label: '订阅计划', value: user.plan_name || '-' },
            { label: '到期时间', value: user.expired_at ? fmtTime(user.expired_at) : '-' }
        ] },
        { fields: [
            { label: '注册IP', value: user.reg_ip || '-', sub: regionSubText(user.reg_ip_info) },
            { label: '注册时间', value: user.created_at ? fmtTime(user.created_at) : '-' }
        ] },
        { fields: [
            { label: '登录IP', value: user.login_ip || '-', sub: regionSubText(user.login_ip_info) },
            { label: '最近登录', value: user.last_login_at ? fmtTime(user.last_login_at) : '-' }
        ] }
    ];
    document.getElementById('detailSummary').innerHTML = summary.map(function(card) {
        var inner = card.fields.map(function(field) {
            return '<div class="summary-field">'
                + '<div class="label">' + escapeHtml(field.label) + '</div>'
                + '<div class="field-body">'
                + '<div class="value">' + escapeHtml(field.value) + '</div>'
                + (field.sub ? '<div class="sub">' + escapeHtml(field.sub) + '</div>' : '')
                + '</div></div>';
        }).join('');
        return '<div class="summary-card">' + inner + '</div>';
    }).join('');

    document.getElementById('detailSubTip').textContent = detailTip(subLogs.length, data.sub_total);
    document.getElementById('detailAliveTip').textContent = detailTip(aliveIps.length, data.alive_total);

    // 省份对比：两边都有记录时才标红
    var subProvinceSet = {};
    (data.sub_provinces || []).forEach(function(p) { subProvinceSet[p] = true; });
    var aliveProvinceSet = {};
    (data.alive_provinces || []).forEach(function(p) { aliveProvinceSet[p] = true; });
    var canCompare = Object.keys(subProvinceSet).length > 0 && Object.keys(aliveProvinceSet).length > 0;

    function isMismatch(province, otherSet, country) {
        if (!canCompare || !province || specialRegionMap[province]) return false;
        if (country && country !== '中国') return false;
        return !otherSet[province];
    }

    var subBody = document.getElementById('detailSubBody');
    if (!subLogs.length) {
        subBody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无订阅记录</p></div></td></tr>';
    } else {
        subBody.innerHTML = subLogs.map(function(row) {
            var ua = row.user_agent || '';
            var mismatch = isMismatch((row.ip_region || '').trim(), aliveProvinceSet, (row.ip_country || '').trim());
            var regionHtml = mismatch
                ? '<span class="mismatch-province">' + escapeHtml(regionText(row)) + '</span>'
                : escapeHtml(regionText(row));
            return '<tr' + (mismatch ? ' class="mismatch-row"' : '') + '>'
                + '<td class="col-ip">' + detailIpCell(row.ip) + '</td>'
                + '<td class="col-region">' + regionHtml + '</td>'
                + '<td class="user-agent-cell" title="' + escapeHtml(ua) + '">' + escapeHtml(ua ? truncate(ua, 60) : '-') + '</td>'
                + '<td class="col-time">' + fmtTime(row.created_at) + '</td>'
                + '<td class="col-actions"><a href="javascript:void(0)" class="danger-link" title="删除这条订阅记录" onclick="deleteDetailRecord(\'sub\', ' + parseInt(row.id, 10) + ')"><i class="fas fa-trash"></i></a></td>'
                + '</tr>';
        }).join('');
    }

    var aliveBody = document.getElementById('detailAliveBody');
    if (!aliveIps.length) {
        aliveBody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无在线记录</p></div></td></tr>';
    } else {
        aliveBody.innerHTML = aliveIps.map(function(row) {
            var regionRow = {
                ip_country: row.country,
                ip_region: row.province,
                ip_city: row.city,
                ip_isp: row.isp
            };
            var mismatch = isMismatch((row.province || '').trim(), subProvinceSet, (row.country || '').trim());
            var regionHtml = mismatch
                ? '<span class="mismatch-province">' + escapeHtml(regionText(regionRow)) + '</span>'
                : escapeHtml(regionText(regionRow));
            return '<tr' + (mismatch ? ' class="mismatch-row"' : '') + '>'
                + '<td class="col-ip">' + detailIpCell(row.ip) + '</td>'
                + '<td class="col-region">' + regionHtml + '</td>'
                + '<td class="col-time">' + fmtTime(row.last_seen_at) + '</td>'
                + '<td class="col-actions"><a href="javascript:void(0)" class="danger-link" title="删除这条在线记录" onclick="deleteDetailRecord(\'alive\', ' + parseInt(row.id, 10) + ')"><i class="fas fa-trash"></i></a></td>'
                + '</tr>';
        }).join('');
    }
}

function deleteDetailRecord(type, id) {
    var label = type === 'sub' ? '订阅记录' : '在线记录';
    if (!confirm('确认删除这条' + label + '？此操作不可恢复。')) return;

    var params = new URLSearchParams();
    params.set('type', type);
    params.set('id', id);
    apiFetch(apiBase + '/user_portrait/deleteRecord', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) {
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
    }).then(function() {
        searchDetail();
    }).catch(function() {
        alert('删除失败');
    });
}

function searchLogs() {
    clearTimeout(filterDebounceTimer);
    state.current = 1;
    state.filters = buildFilters();
    renderFilterChips();
    loadLogData();
}

function filterByUser(userId) {
    document.getElementById('filterUserId').value = userId;
    state.current = 1;
    state.filters = buildFilters();
    renderFilterChips();
    loadLogData();
}

// 点击 User ID 直接切到「用户详情」并查询
function openUserDetail(userId) {
    var btn = document.querySelector('.tab-btn[data-tab="tabDetail"]');
    if (btn) btn.click();
    var input = document.getElementById('detailUserId');
    if (input) input.value = userId;
    searchDetail();
}

function quickFilter(key, value) {
    var controlId = filterControlMap[key];
    if (!controlId) return;
    var control = document.getElementById(controlId);
    if (!control) return;
    control.value = value;
    state.current = 1;
    state.filters = buildFilters();
    renderFilterChips();
    loadLogData();
}

function quickLocationFilter(country, region, isCountry) {
    var countryGroup = '';
    var area = '';
    if (country === '中国') {
        if (['香港', '澳门', '台湾'].indexOf(region) !== -1) {
            countryGroup = 'other';
            area = 'region:' + region;
        } else {
            countryGroup = 'mainland';
            area = isCountry ? '' : region;
        }
    } else if (country) {
        countryGroup = 'other';
        area = 'country:' + country;
    }
    if (!countryGroup) return;

    document.getElementById('filterCountry').value = countryGroup;
    populateRegionOptions(area);
    state.current = 1;
    state.filters = buildFilters();
    renderFilterChips();
    loadLogData();
}

function resetFilters() {
    clearTimeout(filterDebounceTimer);
    document.getElementById('filterDateRange').value = '';
    document.getElementById('filterUserId').value = '';
    document.getElementById('filterUserAgent').value = '';
    document.getElementById('filterIp').value = '';
    document.getElementById('filterCountry').value = '';
    populateRegionOptions('');
    document.getElementById('filterCity').value = '';
    document.getElementById('filterIsp').value = '';
    state.current = 1;
    state.filters = {};
    renderFilterChips();
    loadLogData();
}

// =========== Rules ===========
var editingRuleId = null;

function loadRules() {
    document.getElementById('rulesBody').innerHTML = '<tr><td colspan="13"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';
    apiFetch(apiBase + '/user_portrait/getRules')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || [];
            if (data.length === 0) {
                document.getElementById('rulesBody').innerHTML = '<tr><td colspan="14"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无规则</p></div></td></tr>';
                return;
            }
            var html = '';
            data.forEach(function(r) {
                html += '<tr>'
                    + '<td>' + r.sort + '</td>'
                    + '<td>' + (r.remark || '') + '</td>'
                    + '<td>' + displayRuleValue(r.user_agent) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_country) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_region) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_city) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_isp) + '</td>'
                    + '<td>' + displayRuleValue(r.user_id) + '</td>'
                    + '<td>' + (r.original_host || '<span class="text-muted">全部</span>') + '</td>'
                    + '<td><span class="server-host">' + r.replace_host + '</span></td>'
                    + '<td>' + (r.replace_port || '<span class="text-muted">-</span>') + '</td>'
                    + '<td>' + (parseInt(r.type) === 1 ? '<span class="badge badge-success">白名单</span>' : '<span class="badge badge-type">替换</span>') + '</td>'
                    + '<td>' + (r.enable ? '<span class="badge badge-enable">启用</span>' : '<span class="badge badge-disable">禁用</span>') + '</td>'
                    + '<td class="col-actions">'
                    + '<button class="btn btn-default btn-sm" onclick="editRule(' + r.id + ')"><i class="fas fa-edit"></i></button> '
                    + '<button class="btn btn-danger btn-sm" onclick="deleteRule(' + r.id + ')"><i class="fas fa-trash"></i></button>'
                    + '</td></tr>';
            });
            document.getElementById('rulesBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('rulesBody').innerHTML = '<tr><td colspan="13"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

function openRuleModal(rule) {
    editingRuleId = rule ? rule.id : null;
    document.getElementById('ruleModalTitle').textContent = rule ? '编辑规则' : '添加规则';
    document.getElementById('ruleId').value = rule ? rule.id : '';
    document.getElementById('ruleRemark').value = rule ? (rule.remark || '') : '';
    document.getElementById('ruleSort').value = rule ? (rule.sort || 0) : 0;
    document.getElementById('ruleUserAgent').value = rule ? (rule.user_agent || '') : '';
    document.getElementById('ruleUserId').value = rule ? (rule.user_id || '') : '';
    document.getElementById('ruleCountry').value = rule ? (rule.ip_country || '') : '';
    document.getElementById('ruleRegion').value = rule ? (rule.ip_region || '') : '';
    document.getElementById('ruleCity').value = rule ? (rule.ip_city || '') : '';
    document.getElementById('ruleIsp').value = rule ? (rule.ip_isp || '') : '';
    document.getElementById('ruleOriginalHost').value = rule ? (rule.original_host || '') : '';
    document.getElementById('ruleReplaceHost').value = rule ? (rule.replace_host || '') : '';
    document.getElementById('ruleReplacePort').value = rule ? (rule.replace_port || '') : '';
    document.getElementById('ruleType').value = rule ? (rule.type || 0) : 0;
    document.getElementById('ruleEnable').value = rule ? (rule.enable ? '1' : '0') : '1';
    document.getElementById('ruleModal').classList.add('show');
}

function closeRuleModal() {
    document.getElementById('ruleModal').classList.remove('show');
}

function editRule(id) {
    apiFetch(apiBase + '/user_portrait/getRules')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var rules = res.data || [];
            var rule = rules.find(function(r) { return r.id === id; });
            if (rule) openRuleModal(rule);
        });
}

function deleteRule(id) {
    if (!confirm('确认删除此规则？')) return;
    apiFetch(apiBase + '/user_portrait/dropRule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(function(r) { return r.json(); })
    .then(function() { loadRules(); })
    .catch(function() { alert('删除失败'); });
}

document.getElementById('addRuleBtn').addEventListener('click', function() { openRuleModal(null); });
document.getElementById('saveRuleBtn').addEventListener('click', function() {
    var data = {
        id: editingRuleId || null,
        remark: document.getElementById('ruleRemark').value.trim(),
        sort: parseInt(document.getElementById('ruleSort').value) || 0,
        user_agent: document.getElementById('ruleUserAgent').value.trim() || null,
        user_id: document.getElementById('ruleUserId').value.trim() || null,
        ip_country: document.getElementById('ruleCountry').value.trim() || null,
        ip_region: document.getElementById('ruleRegion').value.trim() || null,
        ip_city: document.getElementById('ruleCity').value.trim() || null,
        ip_isp: document.getElementById('ruleIsp').value.trim() || null,
        original_host: document.getElementById('ruleOriginalHost').value.trim() || null,
        replace_host: document.getElementById('ruleReplaceHost').value.trim(),
        replace_port: parseInt(document.getElementById('ruleReplacePort').value) || null,
        type: parseInt(document.getElementById('ruleType').value),
        enable: parseInt(document.getElementById('ruleEnable').value)
    };
    if (!data.replace_host) { alert('替换 Host 不能为空'); return; }
    if (!data.remark) { alert('备注不能为空'); return; }

    apiFetch(apiBase + '/user_portrait/saveRule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(function(r) { return r.json(); })
    .then(function() { closeRuleModal(); loadRules(); })
    .catch(function() { alert('保存失败'); });
});

// =========== Tab switch data loading ===========
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tab = this.getAttribute('data-tab');
        if (tab === 'tabDistribution') loadDistribution();
        if (tab === 'tabDetail') initDetailTab();
        if (tab === 'tabRules') loadRules();
        if (tab === 'tabRisk') loadRiskData();
        if (tab === 'tabSettings') initSettingsTab();
    });
});

// =========== Risk Screening ===========
var riskState = { current: 1, pageSize: 10, total: 0, sort: 'risk_count', sortType: 'DESC' };
var riskTypeLabels = {
    mismatch: { text: '省份不一致', cls: 'badge-danger' },
    online_province: { text: '在线多省', cls: 'badge-warning' },
    online_ip: { text: '在线多IP', cls: 'badge-warning' },
    sub_province: { text: '订阅多省', cls: 'badge-warning' },
    overseas: { text: '境外/港澳台', cls: 'badge-danger' }
};

function riskUserLink(userId) {
    openUserDetail(userId);
}

function loadRiskData() {
    var types = [];
    document.querySelectorAll('.risk-type-check:checked').forEach(function(el) {
        types.push(el.value);
    });
    if (!types.length) {
        alert('请至少选择一种风险类型');
        return;
    }

    var provinceThreshold = document.getElementById('riskProvinceThreshold').value || 3;
    var ipThreshold = document.getElementById('riskIpThreshold').value || 5;
    var subProvinceThreshold = document.getElementById('riskSubProvinceThreshold').value || 3;
    var days = document.getElementById('riskDays').value;

    document.getElementById('riskBody').innerHTML = '<tr><td colspan="5"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';

    var params = new URLSearchParams();
    params.set('current', riskState.current);
    params.set('pageSize', riskState.pageSize);
    params.set('types', types.join(','));
    params.set('province_threshold', provinceThreshold);
    params.set('ip_threshold', ipThreshold);
    params.set('sub_province_threshold', subProvinceThreshold);
    params.set('days', days);
    params.set('sort', riskState.sort);
    params.set('sort_type', riskState.sortType);

    apiFetch(apiBase + '/user_portrait/riskCheck?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || [];
            riskState.total = res.total || 0;
            updateRiskPagination();

            if (data.length === 0) {
                document.getElementById('riskBody').innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-check-circle"></i><p>未发现风险用户</p></div></td></tr>';
                return;
            }

            var html = '';
            data.forEach(function(r) {
                var tags = r.risk_tags || [];
                var tagHtml = tags.map(function(tag) {
                    var meta = riskTypeLabels[tag] || { text: tag, cls: 'badge-type' };
                    return '<span class="badge ' + meta.cls + '">' + escapeHtml(meta.text) + '</span>';
                }).join('');
                var subRegions = (r.sub_regions || []).join('、');
                var aliveRegions = (r.alive_provinces || []).join('、');
                html += '<tr>'
                    + '<td><a href="javascript:void(0)" onclick="riskUserLink(' + parseInt(r.user_id, 10) + ')" style="color:#1890ff">' + escapeHtml(r.user_id) + '</a></td>'
                    + '<td><span class="risk-tags">' + tagHtml + '</span></td>'
                    + '<td><span class="risk-provinces" title="' + escapeHtml(subRegions) + '">' + (subRegions ? escapeHtml(subRegions) : '<span class="text-muted">-</span>') + '</span></td>'
                    + '<td>' + escapeHtml(r.ua_count || 0) + '</td>'
                    + '<td><span class="risk-provinces" title="' + escapeHtml(aliveRegions) + '">' + (aliveRegions ? escapeHtml(aliveRegions) : '<span class="text-muted">-</span>') + '</span></td>'
                    + '</tr>';
            });
            document.getElementById('riskBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('riskBody').innerHTML = '<tr><td colspan="5"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

function updateRiskPagination() {
    var pagination = document.getElementById('riskPagination');
    if (riskState.total === 0) { pagination.style.display = 'none'; return; }
    pagination.style.display = 'flex';
    var totalPages = Math.ceil(riskState.total / riskState.pageSize) || 1;
    document.getElementById('riskTotalInfo').textContent = '\u5171 ' + riskState.total + ' \u6761';
    document.getElementById('riskPageInfo').textContent = riskState.current + '/' + totalPages;
    document.getElementById('riskPrevPage').disabled = riskState.current <= 1;
    document.getElementById('riskNextPage').disabled = riskState.current >= totalPages;
}

document.getElementById('clearBtn').addEventListener('click', function() {
    var filters = buildFilters();
    var message = Object.keys(filters).length
        ? '确认清除当前筛选条件下的 ' + (state.total || 0) + ' 条日志？此操作不可恢复。'
        : '当前没有筛选条件，确认清除全部订阅记录？此操作不可恢复。';
    if (!confirm(message)) return;
    var params = new URLSearchParams();
    Object.keys(filters).forEach(function(k) { params.set(k, filters[k]); });
    apiFetch(apiBase + '/user_portrait/clearLogs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) { return r.json(); })
    .then(function() { loadLogData(); })
    .catch(function() { alert('清除失败'); });
});

// =========== Settings ===========
var settingsInited = false;
var rebuildRunning = false;
var settingsProviderOptions = [];
var settingsEnabledProviders = [];

function renderProviders() {
    var box = document.getElementById('providerList');
    if (!box) return;
    box.innerHTML = '';
    if (!settingsProviderOptions.length) {
        box.innerHTML = '<span class="text-muted" style="font-size:13px">暂无可用接口</span>';
        return;
    }
    settingsProviderOptions.forEach(function(provider) {
        var label = document.createElement('label');
        label.className = 'provider-item';
        var checked = settingsEnabledProviders.indexOf(provider.key) !== -1;
        label.innerHTML = '<input type="checkbox" value="' + escapeHtml(provider.key) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(provider.name);
        box.appendChild(label);
    });
}

function collectProviders() {
    var keys = [];
    document.querySelectorAll('#providerList input[type="checkbox"]:checked').forEach(function(el) {
        keys.push(el.value);
    });
    return keys;
}

function selectedSource(name) {
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    return checked ? checked.value : 'local';
}

function setSource(name, value) {
    document.querySelectorAll('input[name="' + name + '"]').forEach(function(el) {
        el.checked = el.value === (value === 'online' ? 'online' : 'local');
    });
}

function initSettingsTab() {
    if (settingsInited) return;
    settingsInited = true;
    document.getElementById('saveSettingsBtn').addEventListener('click', saveSettings);
    document.getElementById('resetSettingsBtn').addEventListener('click', function() {
        document.getElementById('settingIpLookup').value = defaultIpLookup;
        document.getElementById('settingIpDbV4').value = '';
        document.getElementById('settingIpDbV6').value = '';
        setSource('settingSourceV4', 'local');
        setSource('settingSourceV6', 'local');
        settingsEnabledProviders = settingsProviderOptions.map(function(p) { return p.key; });
        renderProviders();
        document.getElementById('testIpResult').textContent = '-';
    });
    document.getElementById('testIpBtn').addEventListener('click', testIpLocation);
    document.getElementById('rebuildBtnV4').addEventListener('click', function() { rebuildLocation(4); });
    document.getElementById('rebuildBtnV6').addEventListener('click', function() { rebuildLocation(6); });
    document.getElementById('settingIpLookup').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') saveSettings();
    });
    renderProviders();
    loadSettings();
}

function loadSettings() {
    apiFetch(apiBase + '/user_portrait/getSettings')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || {};
            var url = data.ip_lookup_url || defaultIpLookup;
            document.getElementById('settingIpLookup').value = url;
            document.getElementById('settingIpDbV4').value = data.ip_db_v4_path || '';
            document.getElementById('settingIpDbV6').value = data.ip_db_v6_path || '';
            setSource('settingSourceV4', data.ip_lookup_source_v4 || 'local');
            setSource('settingSourceV6', data.ip_lookup_source_v6 || 'local');
            settingsProviderOptions = data.ip_providers || [];
            settingsEnabledProviders = data.ip_lookup_providers || settingsProviderOptions.map(function(p) { return p.key; });
            renderProviders();
            ipLookupBase = url;
        })
        .catch(function() {});
}

function testIpLocation() {
    var ip = document.getElementById('testIpInput').value.trim();
    var resultBox = document.getElementById('testIpResult');
    if (!ip) {
        resultBox.textContent = '请输入 IP';
        return;
    }

    var params = new URLSearchParams();
    params.set('ip', ip);
    params.set('ip_db_v4_path', document.getElementById('settingIpDbV4').value.trim());
    params.set('ip_db_v6_path', document.getElementById('settingIpDbV6').value.trim());
    params.set('ip_lookup_source_v4', selectedSource('settingSourceV4'));
    params.set('ip_lookup_source_v6', selectedSource('settingSourceV6'));
    params.set('ip_lookup_providers', JSON.stringify(collectProviders()));

    resultBox.textContent = '测试中...';
    apiFetch(apiBase + '/user_portrait/testIpLocation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) {
        if (r.ok) return r.json();
        return r.json().catch(function() { return {}; }).then(function(err) {
            throw new Error(err.message || ('HTTP ' + r.status));
        });
    }).then(function(res) {
        var d = res.data;
        if (!d) {
            resultBox.textContent = '无解析结果';
            return;
        }
        var text = [d.country, d.region, d.city, d.isp].filter(Boolean).join(' ');
        resultBox.textContent = text || '无解析结果';
    }).catch(function(err) {
        resultBox.textContent = '测试失败：' + (err && err.message ? err.message : '未知错误');
    });
}

function saveSettings() {
    var input = document.getElementById('settingIpLookup');
    var url = input.value.trim() || defaultIpLookup;
    var btn = document.getElementById('saveSettingsBtn');
    btn.disabled = true;

    var params = new URLSearchParams();
    params.set('ip_lookup_url', url);
    params.set('ip_db_v4_path', document.getElementById('settingIpDbV4').value.trim());
    params.set('ip_db_v6_path', document.getElementById('settingIpDbV6').value.trim());
    params.set('ip_lookup_source_v4', selectedSource('settingSourceV4'));
    params.set('ip_lookup_source_v6', selectedSource('settingSourceV6'));
    params.set('ip_lookup_providers', JSON.stringify(collectProviders()));
    apiFetch(apiBase + '/user_portrait/saveSettings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) {
        if (r.ok) return r.json();
        return r.json().catch(function() { return {}; }).then(function(err) {
            throw new Error(err.message || ('HTTP ' + r.status));
        });
    }).then(function() {
        ipLookupBase = url;
        alert('保存成功');
    }).catch(function(err) {
        alert('保存失败：' + (err && err.message ? err.message : '请检查配置'));
    }).finally(function() {
        btn.disabled = false;
    });
}

function rebuildLocation(ipVersion) {
    if (rebuildRunning) return;
    if (!confirm('确认重建 IPv' + ipVersion + ' 缓存？将重新解析订阅记录和在线记录中的 IPv' + ipVersion + ' 归属地。')) return;

    rebuildRunning = true;
    var v4Btn = document.getElementById('rebuildBtnV4');
    var v6Btn = document.getElementById('rebuildBtnV6');
    var status = document.getElementById('rebuildStatus');
    v4Btn.disabled = true;
    v6Btn.disabled = true;
    status.textContent = '准备中...';

    var label = 'IPv' + ipVersion;
    runRebuildTable('sub', ipVersion, 0, 0, 0, function() {
        runRebuildTable('alive', ipVersion, 0, 0, 0, function() {
            rebuildRunning = false;
            v4Btn.disabled = false;
            v6Btn.disabled = false;
            status.textContent = label + ' 重建完成';
        });
    });
}

function rebuildFailed() {
    rebuildRunning = false;
    document.getElementById('rebuildBtnV4').disabled = false;
    document.getElementById('rebuildBtnV6').disabled = false;
}

function runRebuildTable(table, ipVersion, lastId, doneCount, total, onDone) {
    var label = 'IPv' + ipVersion + ' ' + (table === 'sub' ? '订阅记录' : '在线记录');
    var status = document.getElementById('rebuildStatus');
    var params = new URLSearchParams();
    params.set('table', table);
    params.set('ip_version', ipVersion);
    params.set('last_id', lastId);
    params.set('limit', 500);

    apiFetch(apiBase + '/user_portrait/rebuildLocation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) {
        if (r.ok) return r.json();
        return r.json().catch(function() { return {}; }).then(function(err) {
            throw new Error(err.message || ('HTTP ' + r.status));
        });
    }).then(function(res) {
        var data = res.data || {};
        if (data.total) total = data.total;
        var newDone = doneCount + (data.processed || 0);
        status.textContent = label + ' ' + newDone + (total ? ' / ' + total : '');
        if (data.done) {
            onDone();
        } else {
            runRebuildTable(table, ipVersion, data.last_id, newDone, total, onDone);
        }
    }).catch(function(err) {
        rebuildFailed();
        status.textContent = '重建失败：' + (err && err.message ? err.message : '请重试');
    });
}

// =========== Init ===========
document.addEventListener('DOMContentLoaded', function() {
    loadFilterOptions().then(function() {
        var params = new URLSearchParams(window.location.search);
        restoreStateFromUrl();
        renderFilterChips();
        loadLogData();
        if (params.get('tab') === 'detail' && params.get('user_id')) {
            openUserDetail(params.get('user_id'));
        }
    });

    document.getElementById('searchBtn').addEventListener('click', searchLogs);
    document.getElementById('resetBtn').addEventListener('click', resetFilters);

    ['filterUserId', 'filterUserAgent', 'filterIp', 'filterCity', 'filterIsp'].forEach(function(id) {
        var input = document.getElementById(id);
        input.addEventListener('input', scheduleLogSearch);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchLogs();
            }
        });
    });
    ['filterDateRange', 'filterRegion', 'filterIsp'].forEach(function(id) {
        document.getElementById(id).addEventListener('change', searchLogs);
    });
    document.getElementById('filterCountry').addEventListener('change', function() {
        populateRegionOptions('');
        searchLogs();
    });

    document.getElementById('filterChips').addEventListener('click', function(e) {
        if (e.target.id === 'clearAllFilters') {
            resetFilters();
            return;
        }
        if (e.target.classList.contains('remove')) {
            var key = e.target.getAttribute('data-key');
            var controlId = filterControlMap[key];
            if (key === 'country_group') {
                document.getElementById(controlId).value = '';
                populateRegionOptions('');
            } else if (controlId) {
                document.getElementById(controlId).value = '';
            }
            searchLogs();
        }
    });

    document.getElementById('tableBody').addEventListener('click', function(e) {
        var link = e.target.closest('.quick-filter');
        if (!link) return;
        e.preventDefault();
        var filter = link.getAttribute('data-filter');
        if (filter === 'country' || filter === 'region') {
            quickLocationFilter(
                link.getAttribute('data-country') || '',
                link.getAttribute('data-region') || '',
                filter === 'country'
            );
        } else {
            quickFilter(filter, link.getAttribute('data-value'));
        }
    });

    document.getElementById('prevPage').addEventListener('click', function() {
        if (state.current > 1) { state.current--; loadLogData(); }
    });
    document.getElementById('nextPage').addEventListener('click', function() {
        var totalPages = Math.ceil(state.total / state.pageSize);
        if (state.current < totalPages) { state.current++; loadLogData(); }
    });
    document.getElementById('pageSizeSelect').addEventListener('change', function() {
        state.pageSize = parseInt(this.value);
        state.current = 1;
        loadLogData();
    });

    document.getElementById('riskSearchBtn').addEventListener('click', function() {
        riskState.current = 1;
        loadRiskData();
    });
    ['riskProvinceThreshold', 'riskIpThreshold', 'riskSubProvinceThreshold'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('keydown', function(e) { if (e.key === 'Enter') { riskState.current = 1; loadRiskData(); } });
    });
    document.getElementById('riskDays').addEventListener('change', function() {
        riskState.current = 1;
        loadRiskData();
    });
    document.getElementById('riskPrevPage').addEventListener('click', function() {
        if (riskState.current > 1) { riskState.current--; loadRiskData(); }
    });
    document.getElementById('riskNextPage').addEventListener('click', function() {
        var totalPages = Math.ceil(riskState.total / riskState.pageSize);
        if (riskState.current < totalPages) { riskState.current++; loadRiskData(); }
    });
    document.querySelectorAll('.risk-sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var key = this.getAttribute('data-sort');
            if (riskState.sort === key) {
                riskState.sortType = riskState.sortType === 'DESC' ? 'ASC' : 'DESC';
            } else {
                riskState.sort = key;
                riskState.sortType = 'DESC';
            }
            document.querySelectorAll('.risk-sortable').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            riskState.current = 1;
            loadRiskData();
        });
    });

    document.querySelectorAll('th.sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var key = this.getAttribute('data-sort');
            if (state.sort === key) {
                state.sortType = state.sortType === 'DESC' ? 'ASC' : 'DESC';
            } else {
                state.sort = key;
                state.sortType = 'DESC';
            }
            document.querySelectorAll('th.sortable').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            state.current = 1;
            loadLogData();
        });
    });
});
</script>
</body>
</html>
