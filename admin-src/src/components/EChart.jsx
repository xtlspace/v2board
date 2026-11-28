import React, { useEffect, useRef } from 'react'
import * as echarts from 'echarts'

export default function EChart({ option, style = { height: 320 }, notMerge = true }) {
  const ref = useRef(null)
  const chartRef = useRef(null)

  useEffect(() => {
    if (!ref.current) return undefined
    const chart = echarts.init(ref.current, 'vintage', { renderer: 'svg' })
    chartRef.current = chart
    const onResize = () => chart.resize()
    window.addEventListener('resize', onResize)
    return () => {
      window.removeEventListener('resize', onResize)
      chart.dispose()
      chartRef.current = null
    }
  }, [])

  useEffect(() => {
    if (chartRef.current && option) chartRef.current.setOption(option, notMerge)
  }, [option, notMerge])

  return <div ref={ref} style={style} />
}
