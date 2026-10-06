import * as React from "react";
import * as SwitchPrimitive from "@radix-ui/react-switch";

export function Switch({
  checked,
  onCheckedChange,
  ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root>) {
  return (
    <SwitchPrimitive.Root
      checked={checked}
      onCheckedChange={onCheckedChange}
      style={{
        width: "52px",
        height: "32px",
        borderRadius: "16px",
        backgroundColor: checked ? "#183D69" : "#C2D1E1",
        border: "none",
        cursor: "pointer",
        position: "relative",
        transition: "background-color 0.2s ease-in-out",
        padding: 0,
        outline: "none",
        WebkitTapHighlightColor: "transparent",
      }}
      {...props}
    >
      <SwitchPrimitive.Thumb
        style={{
          width: "24px",
          height: "24px",
          borderRadius: "16px",
          backgroundColor: checked ? "#F7B233" : "#fff",
          position: "absolute",
          top: "4px",
          left: checked ? "24px" : "4px",
          transition: "left 0.2s ease-in-out",
        }}
      />
    </SwitchPrimitive.Root>
  );
}
