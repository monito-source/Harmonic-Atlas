import { useEffect, useState } from 'react'

export default function TextCommitInput({
  value,
  onCommit,
  parse = (nextValue) => nextValue,
  format = (nextValue) => (nextValue ?? ''),
  ...props
}) {
  const formattedValue = format(value)
  const [draft, setDraft] = useState(formattedValue)

  useEffect(() => {
    setDraft((current) => (current === formattedValue ? current : formattedValue))
  }, [formattedValue])

  const commit = () => {
    onCommit?.(parse(draft))
  }

  return (
    <input
      {...props}
      value={draft}
      onChange={(event) => {
        setDraft(event.target.value)
      }}
      onBlur={(event) => {
        commit()
        props.onBlur?.(event)
      }}
      onKeyDown={(event) => {
        if (event.key === 'Escape') {
          setDraft(formattedValue)
          event.currentTarget.blur()
          return
        }
        props.onKeyDown?.(event)
      }}
    />
  )
}
