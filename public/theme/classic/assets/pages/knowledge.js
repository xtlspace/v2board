import { h, raw, formatDate, debounce, icon } from '../lib/dom.js';
import { t, getLanguage } from '../lib/i18n.js';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';
import { button, card, emptyBlock, errorBlock, loadingBlock, tag } from '../lib/ui.js';

function articleRow(article) {
    return h('div', {
        class: 'kb-row',
        role: 'link',
        tabindex: '0',
        onClick: () => navigate(`/knowledge/${article.id}`),
        onKeydown: event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigate(`/knowledge/${article.id}`);
            }
        }
    },
        h('div', { class: 'kb-row-title' }, article.title),
        h('div', { class: 'kb-row-date' }, t('最后更新: {date}', { date: formatDate(article.updated_at) }))
    );
}

function categoryCard(category, articles) {
    return h('section', { class: 'card kb-card' },
        h('div', { class: 'card-head' }, h('h2', { class: 'card-title' }, category)),
        h('div', { class: 'kb-rows' }, articles.map(articleRow))
    );
}

export async function mount(el) {
    el.replaceChildren(loadingBlock());
    const state = { keyword: '', groups: {} };

    const content = h('div', {});

    const render = () => {
        const categories = Object.keys(state.groups);
        if (!categories.length) {
            content.replaceChildren(card({
                body: emptyBlock({
                    iconName: 'book',
                    title: t('未找到相关文档'),
                    desc: t('换个关键词试试')
                })
            }));
            return;
        }
        content.replaceChildren(h('div', { class: 'stack' },
            categories.map(category => categoryCard(category, state.groups[category] || []))
        ));
    };

    const load = async () => {
        try {
            const response = await api.get('/user/knowledge/fetch', {
                language: getLanguage(),
                keyword: state.keyword || undefined
            });
            state.groups = (response && response.data) || {};
        } catch (error) {
            content.replaceChildren(errorBlock(error, () => load()));
            return;
        }
        render();
    };

    const searchInput = h('input', {
        class: 'kb-search-input',
        type: 'search',
        placeholder: t('搜索文档'),
        onInput: debounce(() => {
            state.keyword = searchInput.value.trim();
            load();
        }, 350),
        onKeydown: event => {
            if (event.key === 'Enter') {
                state.keyword = searchInput.value.trim();
                load();
            }
        }
    });

    const searchBlock = h('div', { class: 'kb-search-block' },
        searchInput,
        h('button', {
            class: 'kb-search-btn',
            type: 'button',
            'aria-label': t('搜索'),
            onClick: () => {
                state.keyword = searchInput.value.trim();
                load();
            }
        }, icon('search', 18))
    );

    await load();

    el.replaceChildren(h('div', { class: 'stack' }, searchBlock, content));
}

export async function mountDetail(el, ctx) {
    el.replaceChildren(loadingBlock());
    const id = ctx.params.id;

    try {
        const response = await api.get('/user/knowledge/fetch', { id });
        const article = response ? response.data : null;
        if (!article) throw new Error(t('文章不存在'));

        el.replaceChildren(h('div', { class: 'stack' },
            h('div', { class: 'flex-between flex-wrap' },
                h('div', {},
                    h('h1', { class: 'page-title' }, article.title),
                    h('div', { class: 'page-desc' },
                        tag(article.category || '', 'muted'),
                        h('span', { class: 'faint' }, ' · '),
                        t('最后更新: {date}', { date: formatDate(article.updated_at) })
                    )
                ),
                button(t('返回列表'), { iconName: 'arrow-left', onClick: () => navigate('/knowledge') })
            ),
            card({
                title: t('使用文档'),
                body: h('article', { class: 'article-body' }, raw(article.body || ''))
            })
        ));
    } catch (error) {
        el.replaceChildren(errorBlock(error, () => mountDetail(el, ctx)));
    }
}
